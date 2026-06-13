<?php

namespace Tests\Feature\Legal;

use Tests\TestCase;

final class OfferPageTest extends TestCase
{
    public function test_offer_page_is_available_from_plural_url_used_by_links(): void
    {
        $this
            ->get('/offers')
            ->assertOk();
    }
}
