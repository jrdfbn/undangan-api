<?php

namespace App\Controllers\Api;

use App\Repositories\GuestContract;
use App\Response\JsonResponse;
use Core\Auth\Auth;
use Core\Http\Respond;
use Core\Routing\Controller;
use Core\Http\Request;
use Core\Http\Stream;

class GuestController extends Controller
{
    private $json;

    public function __construct(JsonResponse $json)
    {
        $this->json = $json;
    }

    public function index(GuestContract $guest): JsonResponse
    {
        $lists = [];
        foreach ($guest->findAllByUserID(Auth::id()) as $row) {
            $lists[] = [
                'id' => $row->id,
                'name' => $row->name,
                'token' => $row->token,
                'rsvp_status' => $row->rsvp_status,
                'guest_count' => $row->guest_count,
                'created_at' => $row->created_at,
            ];
        }

        return $this->json->successOK([
            'lists' => $lists,
        ]);
    }

    public function store(Request $request, GuestContract $guest): JsonResponse
    {
        $valid = $this->validate($request, [
            'name' => ['required', 'str', 'trim', 'min:1', 'max:50'],
        ]);

        if ($valid->fails()) {
            return $this->json->errorBadRequest($valid->messages());
        }

        $row = $guest->create(Auth::id(), $valid->name);

        return $this->json->success($row->only(['id', 'name', 'token', 'rsvp_status', 'guest_count']), Respond::HTTP_CREATED);
    }

    public function import(Request $request, GuestContract $guest): JsonResponse
    {
        $valid = $this->validate($request, [
            'csv' => ['required', 'str', 'max:500000'],
        ]);

        if ($valid->fails()) {
            return $this->json->errorBadRequest($valid->messages());
        }

        return $this->json->successOK($guest->createManyFromCsv(Auth::id(), $valid->csv));
    }

    public function update(string $id, Request $request, GuestContract $guest): JsonResponse
    {
        $valid = $this->validate($request, [
            'name' => ['required', 'str', 'trim', 'min:1', 'max:50'],
        ]);

        if ($valid->fails()) {
            return $this->json->errorBadRequest($valid->messages());
        }

        $status = $guest->updateById(Auth::id(), intval($id), ['name' => $valid->name]);

        if ($status === 1) {
            return $this->json->successStatusTrue();
        }

        if ($status === 0) {
            return $this->json->errorNotFound();
        }

        return $this->json->errorServer();
    }

    public function destroy(string $id, GuestContract $guest): JsonResponse
    {
        $status = $guest->deleteById(Auth::id(), intval($id));

        if ($status === 1) {
            return $this->json->successStatusTrue();
        }

        if ($status === 0) {
            return $this->json->errorNotFound();
        }

        return $this->json->errorServer();
    }

    public function export(Stream $stream, GuestContract $guest): Stream
    {
        $streamResource = $stream->getStream();

        fputcsv($streamResource, [
            'id',
            'name',
            'token',
            'rsvp_status',
            'guest_count',
        ]);

        foreach ($guest->findAllByUserID(Auth::id()) as $row) {
            fputcsv($streamResource, [
                $row->id,
                $row->name,
                $row->token,
                $row->rsvp_status,
                $row->guest_count,
            ]);
        }

        return $stream->create(sprintf('guests_%s.csv', now('y-m-d_H:i:s')))->download();
    }
}