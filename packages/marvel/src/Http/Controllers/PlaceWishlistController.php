<?php

namespace Marvel\Http\Controllers;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Marvel\Database\Models\Place;
use Marvel\Exceptions\MarvelException;
use Marvel\Http\Requests\PlaceWishlistCreateRequest;
use Marvel\Database\Repositories\PlaceWishlistRepository;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PlaceWishlistController extends CoreController
{
    public $repository;

    public function __construct(PlaceWishlistRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function index(Request $request)
    {
        $limit = $request->limit ? $request->limit : 15;
        $wishlist = $this->repository->pluck('place_id');
        return Place::whereIn('id', $wishlist)
            ->with(['images', 'videos', 'hashtags', 'user', 'likes', 'products'])
            ->paginate($limit);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param PlaceWishlistCreateRequest $request
     * @return mixed
     * @throws ValidatorException
     */
    public function store(PlaceWishlistCreateRequest $request)
    {
        try {
            return $this->repository->storeWishlist($request);
        } catch (MarvelException $th) {
            throw new MarvelException('Could not create the resource');
        }
    }

    /**
     * Toggle wishlist for place
     *
     * @param PlaceWishlistCreateRequest $request
     * @return mixed
     */
    public function toggle(PlaceWishlistCreateRequest $request)
    {
        try {
            return $this->repository->toggleWishlist($request);
        } catch (MarvelException $th) {
            throw new MarvelException('Something went wrong');
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(Request $request, $id)
    {
        try {
            $request->id = $id;
            return $this->delete($request);
        } catch (MarvelException $th) {
            throw new MarvelException('Could not delete the resource');
        }
    }

    public function delete(Request $request)
    {
        try {
            if (!$request->user()) {
                throw new AuthorizationException('Not authorized');
            }
            $place = Place::where('id', $request->id)->first();
            $wishlist = $this->repository->where('place_id', $place->id)->where('user_id', auth()->user()->id)->first();
            if (!empty($wishlist)) {
                return $wishlist->delete();
            }
            throw new HttpException(404, 'Not found');
        } catch (MarvelException $th) {
            throw new MarvelException('Could not delete the resource');
        }
    }

    /**
     * Check in wishlist place for authenticated user
     *
     * @param int $place_id
     * @return JsonResponse
     */
    public function in_wishlist(Request $request, $place_id)
    {
        $request->place_id = $place_id;
        return $this->inWishlist($request);
    }

    public function inWishlist(Request $request)
    {
        if (auth()->user() && !empty($this->repository->where('place_id', $request->place_id)->where('user_id', auth()->user()->id)->first())) {
            return true;
        }
        return false;
    }
}

