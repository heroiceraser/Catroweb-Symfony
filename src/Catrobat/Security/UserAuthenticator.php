<?php

namespace App\Catrobat\Security;

use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Class UserAuthenticator.
 */
class UserAuthenticator
{
  /**
   * @var AuthenticationManagerInterface
   */
  private $authentication_manager;
  /**
   * @var UserProviderInterface
   */
  private $user_provider;

  /**
   * UserAuthenticator constructor.
   */
  public function __construct(UserProviderInterface $user_provider, AuthenticationManagerInterface $authentication_manager)
  {
    $this->authentication_manager = $authentication_manager;
    $this->user_provider = $user_provider;
  }

  /**
   * @param $username
   * @param $password
   *
   * @return TokenInterface
   */
  public function authenticate($username, $password)
  {
    $user = $this->user_provider->loadUserByUsername($username);

    return $this->authentication_manager->authenticate(
      new UsernamePasswordToken($user->getUsername(), $password, 'main'));
  }
}
