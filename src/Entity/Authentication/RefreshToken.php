<?php

namespace App\Entity\Authentication;

use Doctrine\ORM\Mapping as ORM;
use Gesdinet\JWTRefreshTokenBundle\Entity\RefreshToken as BaseRefreshToken;


#[ORM\Entity]
#[ORM\Table("refresh_tokens")]
class RefreshToken extends BaseRefreshToken
{
}
