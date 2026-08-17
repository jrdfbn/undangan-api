<?php

namespace App\Repositories;

use App\Models\Guest;
use Core\Database\DB;
use Core\Database\DataBase;
use Core\Facades\App;
use Core\Model\Model;

class GuestRepositories implements GuestContract
{
    /**
     * Membuat token pendek (8 karakter hex).
     *
     * @return string
     */
    private function generateToken(): string
    {
        return bin2hex(random_bytes(4));
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

        if (count($names) === 0) {
            return [
                'created' => [],
                'skipped' => max(0, $attempted)
            ];
        }

        $taken = [];
        $tokens = [];
        foreach ($names as $index => $name) {
            do {
                $token = $this->generateToken();
            } while (isset($taken[$token]));

            $taken[$token] = true;
            $tokens[$index] = $token;
        }

        $created = count($names);
        $now = now('Y-m-d H:i:s.u');
        $rows = [];

        foreach ($names as $index => $name) {
            $rows[] = [$user_id, $name, $tokens[$index], 'pending', null, $now, $now];
        }

        DB::transaction(function () use ($rows): void {
            $db = App::get()->singleton(DataBase::class);

            foreach (array_chunk($rows, 300) as $batch) {
                $sql = 'INSERT INTO guests (user_id, name, token, rsvp_status, guest_count, created_at, updated_at) VALUES '
                    . implode(', ', array_fill(0, count($batch), '(?, ?, ?, ?, ?, ?, ?)')) . ';';

                $db->query($sql);

                $values = [];
                foreach ($batch as $row) {
                    foreach ($row as $value) {
                        $values[] = $value;
                    }
                }

                foreach ($values as $position => $value) {
                    $db->bind($position + 1, $value);
                }

                $db->execute();
            }
        });

        $createdLists = [];
        foreach ($names as $index => $name) {
            $createdLists[] = [
                'name' => $name,
                'token' => $tokens[$index],
                'rsvp_status' => 'pending',
                'guest_count' => null,
            ];
        }

        return [
            'created' => $createdLists,
            'skipped' => max(0, $attempted - $created)
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