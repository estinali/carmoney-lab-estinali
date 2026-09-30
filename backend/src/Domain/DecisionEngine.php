<?php

declare(strict_types=1);

namespace CarMoneyLab\Domain;

/**
 * Решение по заявке на основании LTV и пробега.
 *
 *   LTV <= approve_max                                 -> approve
 *   approve_max < LTV <= review_max                    -> review
 *   LTV > review_max                                   -> reject
 *
 * Плюс правило пробега: если mileage > max_mileage_for_approve_km,
 * исход approve превращается в review. Ветка пробега стоит после
 * reject и до approve, чтобы не понижать reject до review и
 * перекрывать approve.
 */
final class DecisionEngine
{
    public const APPROVE = 'approve';
    public const REVIEW = 'review';
    public const REJECT = 'reject';

    private float $approveMax;
    private float $reviewMax;
    private int $maxMileageForApproveKm;

    /** @param array{approve_max:float,review_max:float,max_mileage_for_approve_km:int} $thresholds */
    public function __construct(array $thresholds)
    {
        $this->approveMax = $thresholds['approve_max'];
        $this->reviewMax = $thresholds['review_max'];
        $this->maxMileageForApproveKm = $thresholds['max_mileage_for_approve_km'];
    }

    public function decide(float $ltv, int $mileage): string
    {
        if ($ltv > $this->reviewMax) {
            return self::REJECT;
        }

        if ($mileage > $this->maxMileageForApproveKm) {
            return self::REVIEW;
        }

        if ($ltv < $this->approveMax) {
            return self::APPROVE;
        }

        return self::REVIEW;
    }
}
