<?php

namespace App\Repositories;

use Core\Model\Model;

interface GuestContract
{
    public function getByToken(string $token): Model;
    public function findAllByUserID(int $user_id): Model;
    public function findById(int $user_id, int $id): Model;
    public function create(int $user_id, string $name, string $rsvp_status = 'pending'): Model;
    public function createManyFromCsv(int $user_id, string $csv): array;
    public function updateById(int $user_id, int $id, array $data): int;
    public function deleteById(int $user_id, int $id): int;
    public function updateStatusByGuestId(int $guest_id, string $status, ?int $guest_count = null): void;
}