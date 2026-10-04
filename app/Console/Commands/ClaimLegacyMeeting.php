<?php

namespace App\Console\Commands;

use App\Models\Meeting;
use App\Models\Player;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('meetings:claim-legacy {email}')]
#[Description('Vincula encontros e jogadores antigos a uma conta já cadastrada.')]
class ClaimLegacyMeeting extends Command
{
    public function handle(): int
    {
        $user = User::query()->where('email', (string) $this->argument('email'))->first();

        if (! $user) {
            $this->error('Cadastre a conta antes de vincular os dados antigos.');

            return self::FAILURE;
        }

        [$meetings, $players] = DB::transaction(fn (): array => [
            Meeting::query()->whereNull('user_id')->update(['user_id' => $user->id]),
            Player::query()->whereNull('user_id')->update(['user_id' => $user->id]),
        ]);

        $this->info("{$meetings} encontro(s) e {$players} jogador(es) vinculados à conta {$user->email}.");

        return self::SUCCESS;
    }
}
