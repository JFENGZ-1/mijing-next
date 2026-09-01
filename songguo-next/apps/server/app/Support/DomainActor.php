<?php

namespace App\Support;

use App\Models\Staff;
use App\Models\SuperAdmin;
use InvalidArgumentException;

final readonly class DomainActor
{
    private function __construct(
        public string $type,
        public ?int $id,
    ) {
        if (! in_array($type, ['staff', 'super_admin', 'account', 'system'], true)) {
            throw new InvalidArgumentException('Unsupported domain actor type.');
        }
    }

    public static function staff(Staff $staff): self
    {
        return new self('staff', $staff->id);
    }

    public static function superAdmin(SuperAdmin|int $admin): self
    {
        return new self('super_admin', $admin instanceof SuperAdmin ? $admin->id : $admin);
    }

    public static function account(int $accountId): self
    {
        return new self('account', $accountId);
    }

    public static function system(): self
    {
        return new self('system', null);
    }

    public function staffId(): ?int
    {
        return $this->type === 'staff' ? $this->id : null;
    }

    public function metadata(): array
    {
        return ['actorType' => $this->type, 'actorId' => $this->id];
    }
}
