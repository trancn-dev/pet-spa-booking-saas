<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsOnTenant;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature', 'Arch');
pest()->extend(TestCase::class)->in('Unit');
pest()->use(ActsOnTenant::class)->in('Feature/ExampleTest.php');
