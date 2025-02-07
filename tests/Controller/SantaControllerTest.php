<?php

/*
 * This file is part of the Secret Santa project.
 *
 * (c) JoliCode <coucou@jolicode.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace JoliCode\SecretSanta\Tests\Controller;

use JoliCode\SecretSanta\Model\SecretSanta;
use JoliCode\SecretSanta\Model\User;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SantaControllerTest extends BaseWebTestCase
{
    use SessionPrepareTrait;

    public function testRunPageRedirectsToAuthPage(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/run/slack');
        $response = $client->getResponse();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/auth/slack', $response->getTargetUrl());
    }

    public function testFinishPageReturns404WithoutHash(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/finish');
        $response = $client->getResponse();

        self::assertSame(404, $response->getStatusCode());
    }

    public function testFinishPageWorksWithInvalidHash(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/finish/13456');
        $response = $client->getResponse();

        self::assertSame(404, $response->getStatusCode());
    }

    public function testFinishPageWorksWithValidHashForSuccessfulSecretSanta(): void
    {
        $secretSanta = new SecretSanta('my_application', 'toto', 'azerty', [
            'toto1' => new User('toto1', 'Toto 1'),
            'toto2' => new User('toto2', 'Toto 2'),
            'toto3' => new User('toto3', 'Toto 3'),
        ], [
            'toto1' => 'toto2',
            'toto2' => 'toto3',
        ], null, null);
        $secretSanta->markAssociationAsProceeded('toto1');
        $secretSanta->markAssociationAsProceeded('toto2');

        $client = static::createClient();
        $this->prepareSession($client, 'secret-santa-azerty', $secretSanta);

        $crawler = $client->request('GET', '/finish/azerty');
        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $crawler->filter('html:contains("Well done! All messages were sent")'));
    }

    public function testFinishPageWorksWithValidHashForFailedSecretSanta(): void
    {
        $secretSanta = new SecretSanta('my_application', 'toto', 'azerty', [
            'toto1' => new User('toto1', 'Toto 1'),
            'toto2' => new User('toto2', ''),
            'toto3' => new User('toto3', 'Toto 3'),
        ], [
            'toto1' => 'toto2',
            'toto2' => 'toto3',
        ], null, null);
        $secretSanta->addError('Knock knock. Who\'s there? A santa error!');

        $client = static::createClient();
        $this->prepareSession($client, 'secret-santa-azerty', $secretSanta);

        $crawler = $client->request('GET', '/finish/azerty');
        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertCount(1, $crawler->filter('html:contains("All the messages are not sent yet, please read carefully")'));
        self::assertCount(1, $crawler->filter('html:contains("Knock knock. Who\'s there? A santa error!")'));
        self::assertCount(1, $crawler->filter('html:contains("Toto 1 must offer a gift to xxxxx")'));
        self::assertCount(1, $crawler->filter('html:contains("toto2 must offer a gift to xxxxx")'));
    }

    public function testSpoilWorksWithValidCode(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/spoil');
        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode());

        $form = $crawler->selectButton('Decode!')->form();
        $form['code']->setValue('v2@eyJUb3RvIDEiOiJUb3RvIDIiLCJUb3RvIDIiOiJUb3RvIDMiLCJUb3RvIDMiOiJUb3RvIDEifQ==');

        $crawler = $client->submit($form);
        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Here is the secret repartition', $response->getContent());
        self::assertStringContainsString('<strong>Toto 1</strong> must offer a gift to <strong>Toto 2</strong>', $response->getContent());
        self::assertStringContainsString('<strong>Toto 2</strong> must offer a gift to <strong>Toto 3</strong>', $response->getContent());
        self::assertStringContainsString('<strong>Toto 3</strong> must offer a gift to <strong>Toto 1</strong>', $response->getContent());
    }

    public function testSpoilWorksWithInvalidCode(): void
    {
        $client = static::createClient();

        $crawler = $client->request('GET', '/spoil');
        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode());

        $form = $crawler->selectButton('Decode!')->form();
        $form['code']->setValue('v2@yolo');

        $crawler = $client->submit($form);
        $response = $client->getResponse();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Content could not be decoded', $response->getContent());
    }
}
