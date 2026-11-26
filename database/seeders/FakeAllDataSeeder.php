<?php

namespace Database\Seeders;

use App\Models\Author;
use App\Models\Book;
use App\Models\BookRequest;
use App\Models\BookRequestVote;
use App\Models\Genre;
use App\Models\Loan;
use App\Models\NavNotification;
use App\Models\Publisher;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\SearchLog;
use App\Models\Setting;
use App\Models\User;
use App\Services\MemberCardService;
use App\Services\NavBadgeService;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class FakeAllDataSeeder extends Seeder
{
    /**
     * Append fake records to every application table without changing existing accounts.
     */
    public function run(MemberCardService $memberCards): void
    {
        $this->seedSettings();

        // Faker's GenreFactory has eight unique base values, so keep the genre count at eight.
        $genreIds = Genre::factory()->count(8)->create()->pluck('id');
        $publisherIds = Publisher::factory()->count(60)->create()->pluck('id');
        $authorIds = Author::factory()->count(60)->create()->pluck('id');

        $bookIds = Book::factory()
            ->count(300)
            ->sequence(fn (): array => [
                'genre_id' => $genreIds->random(),
                'publisher_id' => $publisherIds->random(),
                'author_id' => $authorIds->random(),
                // The model serializes themes as comma-separated text, but the
                // column enforces valid JSON; keep seeded books nullable.
                'themes' => null,
            ])
            ->create()
            ->pluck('id');

        $newMembers = User::factory()
            ->count(150)
            ->sequence(fn (Sequence $sequence): array => [
                'username' => 'fake_member_'.$sequence->index.'_'.Str::lower(Str::random(8)),
            ])
            ->create();

        foreach ($newMembers as $newMember) {
            $memberCards->issue($newMember);
        }

        $userIds = User::query()->pluck('id');
        $adminId = User::query()->where('role', User::ROLE_ADMIN)->value('id');

        $this->seedLoans($userIds, $bookIds);
        $this->seedReviews($userIds, $bookIds);
        $this->seedReservations($userIds, $bookIds);

        $requestIds = $this->seedBookRequests($userIds, $adminId);

        $this->seedVotes($userIds, $requestIds);
        $this->seedSearchLogs($userIds);
        $this->seedNavNotifications($userIds);
    }

    private function seedSettings(): void
    {
        foreach ([
            Setting::KEY_FINE_PER_DAY => '5000',
            Setting::KEY_GRACE_DAYS => '2',
            Setting::KEY_LOAN_DAYS => '7',
        ] as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    /**
     * @param  Collection<int, int>  $userIds
     * @param  Collection<int, int>  $bookIds
     */
    private function seedLoans(Collection $userIds, Collection $bookIds): void
    {
        Loan::factory()
            ->count(250)
            ->sequence(fn (): array => [
                'user_id' => $userIds->random(),
                'book_id' => $bookIds->random(),
                'status' => fake()->randomElement([
                    Loan::STATUS_BORROWED,
                    Loan::STATUS_BORROWED,
                    Loan::STATUS_APPROVED,
                    Loan::STATUS_RETURNED,
                    Loan::STATUS_PENDING,
                    Loan::STATUS_REJECTED,
                ]),
            ])
            ->create();
    }

    /**
     * @param  Collection<int, int>  $userIds
     * @param  Collection<int, int>  $bookIds
     */
    private function seedReviews(Collection $userIds, Collection $bookIds): void
    {
        Review::factory()
            ->count(300)
            ->sequence(fn (): array => [
                'user_id' => $userIds->random(),
                'book_id' => $bookIds->random(),
            ])
            ->create();
    }

    /**
     * @param  Collection<int, int>  $userIds
     * @param  Collection<int, int>  $bookIds
     */
    private function seedReservations(Collection $userIds, Collection $bookIds): void
    {
        Reservation::factory()
            ->count(120)
            ->sequence(fn (): array => [
                'user_id' => $userIds->random(),
                'book_id' => $bookIds->random(),
                'status' => fake()->randomElement([
                    Reservation::STATUS_PENDING,
                    Reservation::STATUS_APPROVED,
                    Reservation::STATUS_FULFILLED,
                    Reservation::STATUS_CANCELLED,
                ]),
            ])
            ->create();
    }

    /**
     * @param  Collection<int, int>  $userIds
     * @return Collection<int, int>
     */
    private function seedBookRequests(Collection $userIds, ?int $adminId): Collection
    {
        return BookRequest::factory()
            ->count(100)
            ->sequence(function () use ($userIds, $adminId): array {
                $status = fake()->randomElement([
                    BookRequest::STATUS_PENDING,
                    BookRequest::STATUS_APPROVED,
                    BookRequest::STATUS_REJECTED,
                ]);
                $isPending = $status === BookRequest::STATUS_PENDING;

                return [
                    'user_id' => $userIds->random(),
                    'status' => $status,
                    'reviewed_by' => $isPending ? null : $adminId,
                    'review_note' => $isPending ? null : fake()->sentence(),
                ];
            })
            ->create()
            ->pluck('id');
    }

    /**
     * @param  Collection<int, int>  $userIds
     * @param  Collection<int, int>  $requestIds
     */
    private function seedVotes(Collection $userIds, Collection $requestIds): void
    {
        $usedPairs = BookRequestVote::query()
            ->get(['user_id', 'book_request_id'])
            ->mapWithKeys(fn (BookRequestVote $vote): array => [$vote->user_id.'-'.$vote->book_request_id => true])
            ->all();

        for ($created = 0; $created < 150; $created++) {
            do {
                $userId = $userIds->random();
                $requestId = $requestIds->random();
                $pair = $userId.'-'.$requestId;
            } while (isset($usedPairs[$pair]));

            $usedPairs[$pair] = true;

            BookRequestVote::factory()->create([
                'user_id' => $userId,
                'book_request_id' => $requestId,
            ]);
        }
    }

    /**
     * @param  Collection<int, int>  $userIds
     */
    private function seedSearchLogs(Collection $userIds): void
    {
        SearchLog::factory()
            ->count(400)
            ->sequence(fn (): array => [
                'user_id' => fake()->boolean(70) ? $userIds->random() : null,
            ])
            ->create();
    }

    /**
     * @param  Collection<int, int>  $userIds
     */
    private function seedNavNotifications(Collection $userIds): void
    {
        $navKeys = array_values(NavBadgeService::ROUTE_KEYS);

        $usedPairs = NavNotification::query()
            ->get(['user_id', 'nav_key'])
            ->mapWithKeys(fn (NavNotification $notification): array => [$notification->user_id.'-'.$notification->nav_key => true])
            ->all();

        for ($created = 0; $created < 150; $created++) {
            do {
                $userId = $userIds->random();
                $navKey = fake()->randomElement($navKeys);
                $pair = $userId.'-'.$navKey;
            } while (isset($usedPairs[$pair]));

            $usedPairs[$pair] = true;

            NavNotification::factory()->create([
                'user_id' => $userId,
                'nav_key' => $navKey,
            ]);
        }
    }
}
