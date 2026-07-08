<?php

namespace Raikia\SeatSpyHunter\Services;

use Illuminate\Support\Carbon;
use Raikia\SeatSpyHunter\Models\IntelSetting;

class IntelSettings
{
    const LOW_SKILLPOINT_THRESHOLD = 'low_skillpoint_threshold';
    const NEW_CHARACTER_DAYS = 'new_character_days';
    const SHARED_IP_SCORE = 'shared_ip_score';
    const HOSTILE_INTERACTION_SCORE = 'hostile_interaction_score';
    const VPN_SCORE = 'vpn_score';
    const IP_PROVIDER = 'ip_provider';
    const IP_PROVIDER_KEY = 'ip_provider_key';
    const IP_PROVIDER_LIMITED_UNTIL = 'ip_provider_limited_until';
    const REOPEN_REVIEW_ON_NEW_EVIDENCE = 'reopen_review_on_new_evidence';

    const DEFAULT_LOW_SKILLPOINT_THRESHOLD = 5000000;
    const DEFAULT_NEW_CHARACTER_DAYS = 60;
    const DEFAULT_SHARED_IP_SCORE = 20;
    const DEFAULT_HOSTILE_INTERACTION_SCORE = 25;
    const DEFAULT_VPN_SCORE = 30;

    public function lowSkillpointThreshold(): int
    {
        return $this->integer(self::LOW_SKILLPOINT_THRESHOLD, self::DEFAULT_LOW_SKILLPOINT_THRESHOLD, 0);
    }

    public function setLowSkillpointThreshold(int $value): void
    {
        $this->set(self::LOW_SKILLPOINT_THRESHOLD, (string) max(0, $value));
    }

    public function newCharacterDays(): int
    {
        return $this->integer(self::NEW_CHARACTER_DAYS, self::DEFAULT_NEW_CHARACTER_DAYS, 1);
    }

    public function setNewCharacterDays(int $value): void
    {
        $this->set(self::NEW_CHARACTER_DAYS, (string) max(1, $value));
    }

    public function sharedIpScore(): int
    {
        return $this->integer(self::SHARED_IP_SCORE, self::DEFAULT_SHARED_IP_SCORE, 0);
    }

    public function setSharedIpScore(int $value): void
    {
        $this->set(self::SHARED_IP_SCORE, (string) max(0, $value));
    }

    public function hostileInteractionScore(): int
    {
        return $this->integer(self::HOSTILE_INTERACTION_SCORE, self::DEFAULT_HOSTILE_INTERACTION_SCORE, 0);
    }

    public function setHostileInteractionScore(int $value): void
    {
        $this->set(self::HOSTILE_INTERACTION_SCORE, (string) max(0, $value));
    }

    public function vpnScore(): int
    {
        return $this->integer(self::VPN_SCORE, self::DEFAULT_VPN_SCORE, 0);
    }

    public function setVpnScore(int $value): void
    {
        $this->set(self::VPN_SCORE, (string) max(0, $value));
    }

    public function ipProvider(): ?string
    {
        return $this->string(self::IP_PROVIDER);
    }

    public function setIpProvider(?string $value): void
    {
        $this->set(self::IP_PROVIDER, $value);
    }

    public function ipProviderKey(): ?string
    {
        return $this->string(self::IP_PROVIDER_KEY);
    }

    public function setIpProviderKey(?string $value): void
    {
        $this->set(self::IP_PROVIDER_KEY, $value);
    }

    public function ipProviderLimitedUntil(): ?Carbon
    {
        $value = $this->string(self::IP_PROVIDER_LIMITED_UNTIL);

        return $value ? Carbon::parse($value) : null;
    }

    public function markIpProviderLimitedUntil(Carbon $limited_until): void
    {
        $this->set(self::IP_PROVIDER_LIMITED_UNTIL, $limited_until->toIso8601String());
    }

    public function reopenReviewOnNewEvidence(): bool
    {
        return $this->boolean(self::REOPEN_REVIEW_ON_NEW_EVIDENCE, false);
    }

    public function setReopenReviewOnNewEvidence(bool $value): void
    {
        $this->set(self::REOPEN_REVIEW_ON_NEW_EVIDENCE, $value ? '1' : '0');
    }

    private function integer(string $key, int $default, int $minimum): int
    {
        $setting = IntelSetting::find($key);
        $value = $setting ? (int) $setting->value : $default;

        return max($minimum, $value);
    }

    private function string(string $key): ?string
    {
        $setting = IntelSetting::find($key);

        return $setting && filled($setting->value) ? $setting->value : null;
    }

    private function boolean(string $key, bool $default): bool
    {
        $setting = IntelSetting::find($key);

        if (!$setting) {
            return $default;
        }

        return filter_var($setting->value, FILTER_VALIDATE_BOOLEAN);
    }

    private function set(string $key, ?string $value): void
    {
        IntelSetting::updateOrCreate(
            ['setting' => $key],
            ['value' => $value]
        );
    }
}
