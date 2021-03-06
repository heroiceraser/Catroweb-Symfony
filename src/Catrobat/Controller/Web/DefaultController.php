<?php

namespace App\Catrobat\Controller\Web;

use App\Catrobat\Services\FeaturedImageRepository;
use App\Catrobat\Services\StatisticsService;
use App\Catrobat\Services\TestEnv\FakeStatisticsService;
use App\Entity\FeaturedProgram;
use App\Entity\MediaPackage;
use App\Entity\MediaPackageCategory;
use App\Entity\MediaPackageFile;
use App\Entity\User;
use App\Entity\UserManager;
use App\Repository\FeaturedRepository;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

/**
 * Class DefaultController.
 */
class DefaultController extends AbstractController
{
  /**
   * @var StatisticsService | FakeStatisticsService
   */
  private $statistics;

  /**
   * DefaultController constructor.
   */
  public function __construct(StatisticsService $statistics_service)
  {
    $this->statistics = $statistics_service;
  }

  /**
   * @Route("/", name="index", methods={"GET"})
   *
   * @throws \Twig\Error\Error
   *
   * @return Response
   */
  public function indexAction(Request $request, FeaturedImageRepository $image_repository, FeaturedRepository $repository)
  {
    /**
     * @var FeaturedImageRepository
     * @var FeaturedRepository      $repository
     * @var User                    $user
     * @var FeaturedProgram         $item
     */
    $flavor = $request->get('flavor');

    if ('phirocode' === $flavor)
    {
      $featured_items = $repository->getFeaturedItems('pocketcode', 10, 0);
    }
    else
    {
      $featured_items = $repository->getFeaturedItems($flavor, 10, 0);
    }

    $featured = [];
    foreach ($featured_items as $item)
    {
      $info = [];
      if (null !== $item->getProgram())
      {
        if ($flavor)
        {
          $info['url'] = $this->generateUrl('program',
          ['id' => $item->getProgram()->getId(), 'flavor' => $flavor]);
        }
        else
        {
          $info['url'] = $this->generateUrl('program', ['id' => $item->getProgram()->getId()]);
        }
      }
      else
      {
        $info['url'] = $item->getUrl();
      }
      $info['image'] = $image_repository->getWebPath($item->getId(), $item->getImageType());

      $featured[] = $info;
    }

    return $this->render('Index/index.html.twig', [
      'featured' => $featured,
    ]);
  }

  /**
   * @Route("/termsOfUse", name="termsOfUse", methods={"GET"})
   *
   * @throws \Twig\Error\Error
   *
   * @return Response
   */
  public function termsOfUseAction()
  {
    return $this->render('PrivacyAndTerms/termsOfUse.html.twig');
  }

  /**
   * @Route("/privacypolicy", name="privacypolicy", methods={"GET"})
   *
   * @throws \Twig\Error\Error
   *
   * @return Response
   */
  public function privacypolicyAction()
  {
    return $this->render('PrivacyAndTerms/policy.html.twig');
  }

  /**
   * @Route("/licenseToPlay", name="licenseToPlay", methods={"GET"})
   *
   * @throws \Twig\Error\Error
   *
   * @return Response
   */
  public function licenseToPlayAction()
  {
    return $this->render('PrivacyAndTerms/licenseToPlay.html.twig');
  }

  /**
   * @Route("/pocket-library/{package_name}", name="pocket_library", methods={"GET"})
   * @Route("/media-library/{package_name}", name="media_package", methods={"GET"})
   *
   * @param        $package_name
   * @param string $flavor
   *
   * @throws \Twig\Error\Error
   *
   * @return Response
   */
  public function MediaPackageAction(Request $request, $package_name, $flavor, UserManager $user_manager,
                                     EventDispatcherInterface $event_dispatcher)
  {
    /*
     * @var $package  MediaPackage
     * @var $file     MediaPackageFile
     * @var $category MediaPackageCategory
     * @var $user     User
     */

    if (!isset($flavor))
    {
      $flavor = 'pocketcode';
    }

    if ($request->query->get('username') && $request->query->get('token'))
    {
      $username = $request->query->get('username');
      $user = $user_manager->findUserByUsername($username);
      $token_check = $request->query->get('token');
      if ($user->getUploadToken() == $token_check)
      {
        $user = $user_manager->findUserByUsername($username);
        $token = new UsernamePasswordToken($user, null, 'main', $user->getRoles());
        $this->get('security.token_storage')->setToken($token);
        // now dispatch the login event
        $event = new InteractiveLoginEvent($request, $token);
        $event_dispatcher->dispatch($event);
      }
    }
    $em = $this->getDoctrine()->getManager();
    $package = $em->getRepository('\App\Entity\MediaPackage')
      ->findOneBy(['name_url' => $package_name])
    ;

    if (!$package)
    {
      throw $this->createNotFoundException('Unable to find Package entity.');
    }

    $categories = [];
    foreach ($package->getCategories() as $category)
    {
      $files = [];
      $files = $this->generateDownloadUrl($flavor, $category, $files);
      $categories[] = [
        'name' => $category->getName(),
        'files' => $files,
        'priority' => $category->getPriority(),
      ];
    }

    usort($categories, 'comparePriorities');

    return $this->render('MediaLibrary/mediapackage.html.twig', [
      'categories' => $categories,
    ]);
  }

  /**
   * @param $current
   * @param $next
   *
   * @return int
   */
  public function comparePriorities($current, $next)
  {
    if ($current['priority'] == $next['priority'])
    {
      return 0;
    }

    return ($current['priority'] > $next['priority']) ? -1 : 1;
  }

  /**
   * @Route("/click-statistic", name="click_stats", methods={"POST"})
   *
   * @throws ORMException
   * @throws OptimisticLockException
   *
   * @return Response
   */
  public function makeClickStatisticAction(Request $request)
  {
    $type = $_POST['type'];
    $referrer = $request->headers->get('referer');
    $locale = strtolower($request->getLocale());

    if (in_array($type, ['project', 'rec_homepage', 'rec_remix_graph',
      'rec_remix_notification', 'rec_specific_programs', ], true))
    {
      $rec_from_id = $_POST['recFromID'];
      $rec_program_id = $_POST['recID'];
      $is_user_specific_recommendation = isset($_POST['recIsUserSpecific'])
      ? (bool) $_POST['recIsUserSpecific'] : false;
      $is_recommended_program_a_scratch_program = (('rec_remix_graph' == $type)
      && isset($_POST['isScratchProgram'])) ? (bool) $_POST['isScratchProgram'] : false;

      $this->statistics->createClickStatistics($request, $type, $rec_from_id, $rec_program_id, null, null,
        $referrer, $locale, $is_recommended_program_a_scratch_program, $is_user_specific_recommendation);

      return new Response('ok');
    }

    if ('tags' == $type)
    {
      $tag_id = $_POST['recID'];
      $this->statistics->createClickStatistics($request, $type, null, null, $tag_id, null, $referrer, $locale);

      return new Response('ok');
    }

    if ('extensions' == $type)
    {
      $extension_name = $_POST['recID'];
      $this->statistics->createClickStatistics($request, $type, null, null, null, $extension_name, $referrer, $locale);

      return new Response('ok');
    }

    return new Response('error');
  }

  /**
   * @Route("/homepage-click-statistic", name="homepage_click_stats", methods={"POST"})
   *
   * @throws \Exception
   *
   * @return Response
   */
  public function makeNonRecommendedProgramClickStatisticAction(Request $request)
  {
    $type = $_POST['type'];
    $referrer = $request->headers->get('referer');

    $locale = strtolower($request->getLocale());

    if (in_array($type, ['featured', 'newest', 'mostDownloaded', 'scratchRemixes', 'mostViewed', 'random'], true))
    {
      $program_id = $_POST['programID'];
      $this->statistics->createHomepageProgramClickStatistics($request, $type, $program_id, $referrer, $locale);

      return new Response('ok');
    }

    return new Response('error');
  }

  /**
   * @param $flavor
   * @param $category MediaPackageCategory
   * @param $files
   *
   * @return array
   */
  private function generateDownloadUrl($flavor, $category, $files)
  {
    /*
     * @var MediaPackageFile
     */
    foreach ($category->getFiles() as $file)
    {
      $flavors_arr = preg_replace('/ /', '', $file->getFlavor());
      $flavors_arr = explode(',', $flavors_arr);
      if (!$file->getActive() || (null != $file->getFlavor() && !in_array($flavor, $flavors_arr, true)))
      {
        continue;
      }
      $files[] = [
        'id' => $file->getId(),
        'data' => $file,
        'downloadUrl' => $this->generateUrl('download_media', [
          'id' => $file->getId(),
          'fname' => $file->getName(),
        ]
        ),
      ];
    }

    return $files;
  }
}
