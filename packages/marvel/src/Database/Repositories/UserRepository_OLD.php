<?php

namespace Marvel\Database\Repositories;

use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Marvel\Database\Models\User;
use Prettus\Validator\Exceptions\ValidatorException;
use Spatie\Permission\Models\Permission;
use Marvel\Enums\Permission as UserPermission;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;
use Marvel\Mail\ForgetPassword;
use Illuminate\Support\Facades\Mail;
use Marvel\Database\Models\Address;
use Marvel\Database\Models\Profile;
use Marvel\Database\Models\Shop;
use Marvel\Exceptions\MarvelException;
use Marvel\Events\UserRegistered;

class UserRepository extends BaseRepository
{
    /**
     * @var array
     */
    protected $fieldSearchable = [
        'name' => 'like',
        'email' => 'like',
    ];

    /**
     * @var array
     */
    protected $dataArray = [
        'name',
        'email',
        'shop_id'
    ];

    /**
     * Configure the Model
     **/
    public function model()
    {
        return User::class;
    }

    public function boot()
    {
        try {
            $this->pushCriteria(app(RequestCriteria::class));
        } catch (RepositoryException $e) {
        }
    }

    public function storeUser($request)
    {
        try {
            $user = $this->create([
                'name'     => $request->name,
                'email'    => $request->email,
                'password' => Hash::make($request->password),
            ]);
            $user->givePermissionTo(UserPermission::CUSTOMER);
            if (isset($request['address']) && count($request['address'])) {
                $user->address()->createMany($request['address']);
            }
            if (isset($request['profile'])) {
                // Генерируем seller_id автоматически, игнорируем если был передан в запросе
                $profileData = $request['profile'];
                unset($profileData['seller_id']); // Удаляем seller_id из данных, если был передан
                $profileData['seller_id'] = \Marvel\Services\ArticleGeneratorService::generateSellerId();
                $user->profile()->create($profileData);
            } else {
                // Если профиль не передан, создаем его с seller_id
                $sellerId = \Marvel\Services\ArticleGeneratorService::generateSellerId();
                $user->profile()->create([
                    'seller_id' => $sellerId,
                ]);
            }
            $user->profile = $user->profile;
            $user->address = $user->address;
            $user->shop = $user->shop;
            $user->managed_shop = $user->managed_shop;
            
            // Отправляем событие о регистрации пользователя
            event(new UserRegistered($user, UserPermission::CUSTOMER));
            
            return $user;
        } catch (ValidatorException $e) {
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }

    public function updateUser($request, $user)
    {
        try {
            if (isset($request['address']) && count($request['address'])) {
                foreach ($request['address'] as $address) {
                    if (isset($address['id'])) {
                        Address::findOrFail($address['id'])->update($address);
                    } else {
                        $address['customer_id'] = $user->id;
                        Address::create($address);
                    }
                }
            }

            if (isset($request['profile'])) {
                if (isset($request['profile']['id'])) {
                    // Защита: seller_id не может быть изменен через обновление профиля
                    $profileData = $request['profile'];
                    unset($profileData['seller_id']); // Удаляем seller_id из данных обновления
                    Profile::findOrFail($request['profile']['id'])->update($profileData);
                } else {
                    $profile = $request['profile'];
                    $profile['customer_id'] = $user->id;
                    // Если seller_id не указан, генерируем его автоматически
                    if (!isset($profile['seller_id']) || empty($profile['seller_id'])) {
                        $profile['seller_id'] = \Marvel\Services\ArticleGeneratorService::generateSellerId();
                    }
                    Profile::create($profile);
                }
            }
            $user->update($request->only($this->dataArray));
            $user->profile = $user->profile;
            $user->address = $user->address;
            $user->shop = $user->shop;
            $user->managed_shop = $user->managed_shop;
            return $user;
        } catch (ValidationException $e) {
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }

    public function sendResetEmail($email, $token)
    {
        try {
            Mail::to($email)->send(new ForgetPassword($token));
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    /**
     * Update user email and send verification link to the user.
     * @param  $request
     * @return string[]
     */

    public function updateEmail($request): array
    {
        $user =$request->user();
        $user->email = $request->email;
        $user->email_verified_at = null;
        $user->save();
        $user->sendEmailVerificationNotification();
        return ['message' => EMAIL_UPDATED_SUCCESSFULLY, 'status' => 'success'];
    }

}
