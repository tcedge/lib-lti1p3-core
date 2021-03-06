<?php

/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; under version 2
 * of the License (non-upgradable).
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * Copyright (c) 2020 (original work) Open Assessment Technologies SA;
 */

declare(strict_types=1);

namespace OAT\Library\Lti1p3Core\Tests\Unit\Security\Key;

use Lcobucci\JWT\Signer\Key;
use OAT\Library\Lti1p3Core\Security\Key\KeyChain;
use PHPUnit\Framework\TestCase;

class KeyChainTest extends TestCase
{
    /** @var KeyChain */
    private $subject;

    public function setUp(): void
    {
        $this->subject = new KeyChain(
            'identifier',
            'keySetName',
            getenv('TEST_KEYS_ROOT_DIR') . '/RSA/public.key',
            getenv('TEST_KEYS_ROOT_DIR') . '/RSA/private.key',
            'passPhrase'
        );
    }

    public function testGetIdentifier(): void
    {
        $this->assertEquals('identifier', $this->subject->getIdentifier());
    }

    public function testKeySetName(): void
    {
        $this->assertEquals('keySetName', $this->subject->getKeySetName());
    }

    public function testGetPublicKey(): void
    {
        $this->assertEquals(
            new Key(getenv('TEST_KEYS_ROOT_DIR') . '/RSA/public.key'),
            $this->subject->getPublicKey()
        );
    }

    public function testGetPrivateKey(): void
    {
        $this->assertEquals(
            new Key(getenv('TEST_KEYS_ROOT_DIR') . '/RSA/private.key', 'passPhrase'),
            $this->subject->getPrivateKey()
        );
    }

    public function testWithoutPrivateKey(): void
    {
        $subject = new KeyChain(
            'identifier',
            'keySetName',
            getenv('TEST_KEYS_ROOT_DIR') . '/RSA/public.key'
        );

        $this->assertNull($subject->getPrivateKey());
    }
}
