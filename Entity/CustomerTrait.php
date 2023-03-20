<?php

/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Plugin\TwoFactorAuthCustomerApp42\Entity;

use Doctrine\ORM\Mapping as ORM;
use Eccube\Annotation\EntityExtension;

/**
 * @EntityExtension("Eccube\Entity\Customer")
 */
trait CustomerTrait
{
    /**
     * @var ?string
     *
     * @ORM\Column(name="two_factor_auth_secret", type="string", length=255, nullable=true)
     */
    private ?string $two_factor_auth_secret = null;

    /**
     * @return string
     */
    public function getTwoFactorAuthSecret(): ?string
    {
        return $this->two_factor_auth_secret;
    }

    /**
     * @param string|null $two_factor_auth_secret
     */
    public function setTwoFactorAuthSecret(?string $two_factor_auth_secret): void
    {
        $this->two_factor_auth_secret = $two_factor_auth_secret;
    }
}
