<?php

namespace App\Catrobat\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Guard\AbstractGuardAuthenticator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Class ApiTokenAuthenticator.
 */
class ApiTokenAuthenticator extends AbstractGuardAuthenticator
{
  /**
   * @required request parameter TOKEN
   *
   *  Must be sent in the request HEADER containing the user token
   *  Must not be empty
   */
  const TOKEN = 'authenticate';

  /**
   * @var TranslatorInterface
   */
  protected $translator;

  /**
   * @var EntityManagerInterface
   */
  private $em;

  /**
   * ApiTokenAuthenticator constructor.
   */
  public function __construct(EntityManagerInterface $em, TranslatorInterface $translator)
  {
    $this->em = $em;
    $this->translator = $translator;
  }

  /**
   * Called on every request to decide if this authenticator should be
   * used for the request. Returning false will cause this authenticator
   * to be skipped.
   *
   * @return bool
   */
  public function supports(Request $request)
  {
    return $this->requestHasValidAuthTokenInHeader($request);
  }

  /**
   * Called on every request. Return whatever credentials you want to
   * be passed to getUser() as $credentials.
   *
   * @return array|mixed
   */
  public function getCredentials(Request $request)
  {
    return [
      self::TOKEN => $request->headers->get(self::TOKEN),
    ];
  }

  /**
   * @param mixed $credentials
   *
   * @return User|null
   */
  public function getUser($credentials, UserProviderInterface $userProvider)
  {
    $token = $credentials[self::TOKEN];

    if (null === $token || '' === $token)
    {
      return null;
    }

    // if a User object, checkCredentials() is called
    return $this->em->getRepository(User::class)
      ->findOneBy(['upload_token' => $token])
    ;
  }

  /**
   *  Called to make sure the credentials are valid
   *    - E.g mail, username, or password
   *    - no additional checks would also be valid.
   *
   * @param mixed $credentials
   *
   * @return bool
   */
  public function checkCredentials($credentials, UserInterface $user)
  {
    // return true to cause authentication success
    return true;
  }

  /**
   * @param string $providerKey
   *
   * @return Response|null
   */
  public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
  {
    // on success, let the request continue
    return null;
  }

  /**
   * @return JsonResponse|Response|null
   */
  public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
  {
    $data = [
      $this->translator->trans($exception->getMessageKey(), $exception->getMessageData()),
    ];

    return new JsonResponse($data, Response::HTTP_FORBIDDEN);
  }

  /**
   * Called when authentication is needed, but it's not sent.
   *
   * @return JsonResponse|Response
   */
  public function start(Request $request, AuthenticationException $authException = null)
  {
    $data = [
      'Authentication Required',
    ];

    return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
  }

  /**
   * @return bool
   */
  public function supportsRememberMe()
  {
    return false;
  }

  /**
   * @return bool
   */
  private function requestHasValidAuthTokenInHeader(Request $request)
  {
    return $request->headers->has(self::TOKEN) && '' !== $request->headers->get(self::TOKEN);
  }
}
