<?php

namespace App\Interface\Authentication;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

interface JWTManagementInterface
{
    public function getTokenUser(
        UserInterface $user
    );

    public function invalidateToken();

    public function authenticatedUser();

    public function checkIfPasswordIsValid(UserInterface $user,Request $request);
}