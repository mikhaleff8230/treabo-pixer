<?php

namespace Marvel\Database\Repositories;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Marvel\Database\Models\PlaceWishlist;
use Marvel\Exceptions\MarvelException;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PlaceWishlistRepository extends BaseRepository
{
    public function boot()
    {
        try {
            $this->pushCriteria(app(RequestCriteria::class));
        } catch (RepositoryException $e) {
            //
        }
    }

    /**
     * @var array[]
     */
    protected $dataArray = [
        'user_id',
        'place_id'
    ];

    /**
     * Configure the Model
     **/
    public function model()
    {
        return PlaceWishlist::class;
    }

    /**
     * @param $request
     * @return LengthAwarePaginator|JsonResponse|Collection|mixed
     */
    public function storeWishlist($request)
    {
        try {
            $user_id = $request->user()->id;
            $wishlist = $this->findOneWhere((['user_id' => $user_id, 'place_id' => $request['place_id']]));
            if (empty($wishlist)) {
                $request['user_id'] = $user_id;
                $wishlistInput = $request->only($this->dataArray);
                return $this->create($wishlistInput);
            }
        } catch (\Exception $e) {
            throw new HttpException(400, 'Place already added to wishlist');
        }
    }

    /**
     * @param $request
     * @return LengthAwarePaginator|JsonResponse|Collection|mixed
     */
    public function toggleWishlist($request)
    {
        try {
            $user_id = $request->user()->id;
            $wishlist = $this->findOneWhere((['user_id' => $user_id, 'place_id' => $request['place_id']]));
            if (empty($wishlist)) {
                $request['user_id'] = $user_id;
                $wishlistInput = $request->only($this->dataArray);
                $this->create($wishlistInput);
                return true;
            } else {
                $this->delete($wishlist->id);
                return false;
            }
        } catch (\Exception $e) {
            throw new MarvelException('Something went wrong');
        }
    }
}

