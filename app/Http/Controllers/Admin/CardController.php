<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateCardRequest;
use App\Models\MemberCard;
use App\Models\User;
use App\Services\MemberCardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CardController extends Controller
{
    public function __construct(private MemberCardService $memberCards) {}

    public function index(Request $request): View
    {
        return view('admin.cards.index', [
            'cards' => MemberCard::query()
                ->with('user')
                ->when($request->string('q')->toString(), function ($query, string $q): void {
                    $query->where('card_number', 'like', '%'.$q.'%')
                        ->orWhereHas('user', fn ($userQuery) => $userQuery
                            ->whereLike('name', '%'.$q.'%')
                            ->orWhereLike('username', '%'.$q.'%'));
                })
                ->latest()
                ->paginate(12)
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.cards.create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['password']);
        $data['password'] = $request->string('password')->toString();

        $user = User::create($data);

        if ($user->isMember()) {
            $this->memberCards->issue($user);
        }

        return redirect()->route('admin.cards.index')->with('success', 'Akun berhasil dibuat. Kartu anggota dibuat otomatis.');
    }

    public function edit(MemberCard $card): View
    {
        return view('admin.cards.edit', [
            'card' => $card->load('user'),
        ]);
    }

    public function update(UpdateCardRequest $request, MemberCard $card): RedirectResponse
    {
        $this->memberCards->update($card, $request->validated());

        return redirect()->route('admin.cards.index')->with('success', 'Kartu anggota berhasil diperbarui.');
    }

    public function markLost(MemberCard $card): RedirectResponse
    {
        $this->memberCards->markLost($card);

        return back()->with('success', 'Kartu ditandai hilang. Status akun pengguna dinonaktifkan.');
    }

    public function replace(MemberCard $card): RedirectResponse
    {
        $replacement = $this->memberCards->replace($card);

        return back()->with('success', 'Kartu baru diterbitkan: '.$replacement->card_number.'. Akun pengguna diaktifkan kembali.');
    }

    public function destroyAll(): RedirectResponse
    {
        MemberCard::query()->delete();

        return redirect()->route('admin.cards.index')->with('success', 'Semua kartu anggota telah dihapus.');
    }

    public function destroySelected(Request $request): RedirectResponse
    {
        $cardIds = $request->input('card_ids', []);

        if (! $cardIds) {
            return back()->with('error', 'Tidak ada kartu yang dipilih.');
        }

        MemberCard::whereIn('id', $cardIds)->delete();

        return back()->with('success', 'Berhasil menghapus '.count($cardIds).' kartu anggota.');
    }

    public function markCompleted(MemberCard $card): RedirectResponse
    {
        $this->memberCards->deleteCard($card);

        return back()->with('success', "Kartu {$card->card_number} berhasil dihapus.");
    }

    public function markCompletedAll(Request $request): RedirectResponse
    {
        $cardIds = $request->input('card_ids', []);

        if (! $cardIds) {
            return back()->with('error', 'Tidak ada kartu yang dipilih.');
        }

        MemberCard::whereIn('id', $cardIds)->each(function ($card): void {
            $this->memberCards->markCompleted($card);
        });

        return back()->with('success', 'Berhasil menandai '.count($cardIds).' kartu sebagai selesai.');
    }

    public function issue(User $user): RedirectResponse
    {
        if ($user->isAdmin()) {
            return back()->with('error', 'Akun admin tidak memerlukan kartu anggota.');
        }

        $card = $this->memberCards->issue($user);

        return redirect()->route('admin.cards.index')->with('success', 'Kartu anggota berhasil diterbitkan untuk '.$user->name.'. Nomor kartu: '.$card->card_number.'.');
    }
}
