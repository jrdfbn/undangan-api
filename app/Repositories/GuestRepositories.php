<?php

namespace App\Repositories;

use App\Models\Guest;
use Core\Model\Model;
use Core\Valid\Hash;

class GuestRepositories implements GuestContract
{
    /**
     * Membuat token unik yang tidak bentrok.
     *
     * @return string
     */
    private function generateToken(): string
    {
        $token = Hash::rand(16);

        if (Guest::where('token', $token)->first()->exist()) {
            return $this->generateToken();
        }

        return $token;
    }

    public function getByToken(string $token): Model
    {
        return Guest::where('token', $token)->limit(1)->first();
    }

    public function findAllByUserID(int $user_id): Model
    {
        return Guest::where('user_id', $user_id)->orderBy('id', 'DESC')->get();
    }

    public function findById(int $user_id, int $id): Model
    {
        return Guest::where('id', $id)
            ->where('user_id', $user_id)
            ->limit(1)
            ->first();
    }

    public function create(int $user_id, string $name, string $rsvp_status = 'pending'): Model
    {
        return Guest::create([
            'user_id' => $user_id,
            'name' => $name,
            'token' => $this->generateToken(),
            'rsvp_status' => $rsvp_status,
        ]);
    }

    public function createManyFromCsv(int $user_id, string $csv): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $csv);
        $names = [];
        $attempted = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            $line = ltrim($line, "\xEF\xBB\xBF");

            if (str_starts_with($line, '"')) {
                $end = strpos($line, '"', 1);
                $name = $end !== false ? substr($line, 1, $end - 1) : trim($line, "\"");
            } else {
                $parts = explode(',', $line, 2);
                $name = trim($parts[0]);
            }

            if (in_array(mb_strtolower($name), ['name', 'nama'], true)) {
                continue;
            }

            $name = mb_substr($name, 0, 50);
            if ($name === '') {
                continue;
            }

            $attempted++;
            $names[] = $name;
        }

        $names = array_values(array_unique($names));
        $created = [];

        foreach ($names as $name) {
            $row = $this->create($user_id, $name);
            $created[] = [
                'id' => $row->id,
                'name' => $row->name,
                'token' => $row->token,
                'rsvp_status' => $row->rsvp_status,
                'guest_count' => $row->guest_count,
            ];
        }

        return [
            'created' => $created,
            'skipped' => max(0, $attempted - count($created))
        ];
    }

    public function updateById(int $user_id, int $id, array $data): int
    {
        return Guest::where('id', $id)
            ->where('user_id', $user_id)
            ->update($data);
    }

    public function deleteById(int $user_id, int $id): int
    {
        return Guest::where('id', $id)
            ->where('user_id', $user_id)
            ->delete();
    }

    public function updateStatusByGuestId(int $guest_id, string $status, ?int $guest_count = null): void
    {
        $data = ['rsvp_status' => $status];

        if ($guest_count !== null) {
            $data['guest_count'] = $guest_count;
        } elseif ($status === 'berhalangan') {
            $data['guest_count'] = null;
        }

        Guest::where('id', $guest_id)->update($data);
    }
}