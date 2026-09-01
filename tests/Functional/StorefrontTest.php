<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class StorefrontTest extends WebTestCase
{
    public function testRequiredStorefrontBlocksAndControlsAreRendered(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-catalog-toggle]');
        self::assertSelectorExists('[data-carousel-prev]');
        self::assertSelectorExists('[data-carousel-next]');
        self::assertSelectorCount(3, '[data-dot]');
        self::assertSelectorExists('.service');
        self::assertSelectorExists('[data-product]');
        self::assertSelectorExists('[data-topup-form]');
        self::assertSelectorCount(3, '.amount-input [data-currency-option]');
        self::assertSelectorTextContains('.amount-input [data-currency-option][aria-pressed="true"]', '₽');

        $login = $crawler->filter('[data-topup-form] input[name="login"]');
        self::assertCount(1, $login);
        $loginNode = $login->getNode(0);
        self::assertInstanceOf(\DOMElement::class, $loginNode);
        self::assertFalse($loginNode->hasAttribute('required'));
    }
}
