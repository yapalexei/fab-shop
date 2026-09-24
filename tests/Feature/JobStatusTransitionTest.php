<?php

use App\Enums\JobStatus;
use App\Models\Job;

function jobWithStatus(JobStatus $status): Job
{
    return Job::factory()->make(['status' => $status]);
}

test('sequential forward is always allowed', function () {
    expect(jobWithStatus(JobStatus::NotStarted)->canTransitionTo(JobStatus::Cutting))->toBeTrue()
        ->and(jobWithStatus(JobStatus::Cutting)->canTransitionTo(JobStatus::Fitting))->toBeTrue()
        ->and(jobWithStatus(JobStatus::Fitting)->canTransitionTo(JobStatus::Welding))->toBeTrue()
        ->and(jobWithStatus(JobStatus::Welding)->canTransitionTo(JobStatus::Finishing))->toBeTrue()
        ->and(jobWithStatus(JobStatus::Finishing)->canTransitionTo(JobStatus::Shipped))->toBeTrue()
        ->and(jobWithStatus(JobStatus::Shipped)->canTransitionTo(JobStatus::Invoiced))->toBeTrue();
});

test('skipping ahead more than one step is blocked', function () {
    expect(jobWithStatus(JobStatus::NotStarted)->canTransitionTo(JobStatus::Fitting))->toBeFalse()
        ->and(jobWithStatus(JobStatus::Cutting)->canTransitionTo(JobStatus::Finishing))->toBeFalse()
        ->and(jobWithStatus(JobStatus::Welding)->canTransitionTo(JobStatus::Shipped))->toBeFalse();
});

test('a job still in the shop can be moved back to any earlier in-shop state', function () {
    expect(jobWithStatus(JobStatus::Welding)->canTransitionTo(JobStatus::NotStarted))->toBeTrue()
        ->and(jobWithStatus(JobStatus::Welding)->canTransitionTo(JobStatus::Cutting))->toBeTrue()
        ->and(jobWithStatus(JobStatus::Fitting)->canTransitionTo(JobStatus::Cutting))->toBeTrue();
});

test('finishing, shipped, and invoiced cannot be walked back', function () {
    expect(jobWithStatus(JobStatus::Finishing)->canTransitionTo(JobStatus::Welding))->toBeFalse()
        ->and(jobWithStatus(JobStatus::Shipped)->canTransitionTo(JobStatus::Finishing))->toBeFalse()
        ->and(jobWithStatus(JobStatus::Invoiced)->canTransitionTo(JobStatus::Shipped))->toBeFalse();
});

test('invoiced only allows cancellation', function () {
    $job = jobWithStatus(JobStatus::Invoiced);

    expect($job->canTransitionTo(JobStatus::Cancelled))->toBeTrue()
        ->and($job->canTransitionTo(JobStatus::Shipped))->toBeFalse()
        ->and($job->canTransitionTo(JobStatus::NotStarted))->toBeFalse();
});

test('any non-cancelled job can be cancelled', function () {
    foreach (JobStatus::cases() as $status) {
        if ($status === JobStatus::Cancelled) {
            continue;
        }

        expect(jobWithStatus($status)->canTransitionTo(JobStatus::Cancelled))->toBeTrue();
    }
});

test('a cancelled job is terminal', function () {
    $job = jobWithStatus(JobStatus::Cancelled);

    foreach (JobStatus::cases() as $status) {
        expect($job->canTransitionTo($status))->toBeFalse();
    }
});
