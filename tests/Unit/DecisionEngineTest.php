<?php

declare(strict_types=1);

namespace CarMoneyLab\Tests\Unit;

use CarMoneyLab\Domain\DecisionEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DecisionEngineTest extends TestCase
{
    private const MILEAGE_THRESHOLD = 400000;

    private DecisionEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new DecisionEngine([
            'approve_max' => 60.0,
            'review_max' => 85.0,
            'max_mileage_for_approve_km' => self::MILEAGE_THRESHOLD,
        ]);
    }

    #[DataProvider('ltvValues')]
    public function testDecidesByLtv(float $ltv, string $expected): void
    {
        self::assertSame($expected, $this->engine->decide($ltv, 0));
    }

    /** @return array<string,array{float,string}> */
    public static function ltvValues(): array
    {
        return [
            'низкий LTV' => [28.5, DecisionEngine::APPROVE],
            'середина зелёной зоны' => [45.0, DecisionEngine::APPROVE],
            'серая зона' => [72.3, DecisionEngine::REVIEW],
            'верхняя граница серой зоны' => [85.0, DecisionEngine::REVIEW],
            'сразу за верхней границей' => [85.01, DecisionEngine::REJECT],
            'высокий LTV' => [120.0, DecisionEngine::REJECT],
        ];
    }

    #[DataProvider('mileageValues')]
    public function testMileageThresholdWithLowLtv(int $mileage, string $expected): void
    {
        self::assertSame($expected, $this->engine->decide(30.0, $mileage));
    }

    /** @return array<string,array{int,string}> */
    public static function mileageValues(): array
    {
        return [
            'ниже порога' => [399999, DecisionEngine::APPROVE],
            'ровно на пороге' => [400000, DecisionEngine::APPROVE],
            'сразу за порогом' => [400001, DecisionEngine::REVIEW],
        ];
    }

    public function testRejectBeatsReviewWhenBothTriggersFire(): void
    {
        self::assertSame(DecisionEngine::REJECT, $this->engine->decide(120.0, 400001));
    }

    public function testLtvDrivesReviewWhenMileageAtThreshold(): void
    {
        self::assertSame(DecisionEngine::REVIEW, $this->engine->decide(72.3, 400000));
    }

    public function testLtvDrivesRejectWhenMileageBelowThreshold(): void
    {
        self::assertSame(DecisionEngine::REJECT, $this->engine->decide(120.0, 399999));
    }
}
