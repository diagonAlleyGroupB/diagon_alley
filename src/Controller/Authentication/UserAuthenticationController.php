<?php

namespace App\Controller\Authentication;
use App\DTO\AuthenticationDTO\LoginDTO;
use App\Interface\Authentication\JWTManagementInterface;
use App\Interface\Wallet\WalletServiceInterface;
use App\Repository\UserRepository\UserRepository;
use App\Service\UserService\UserService;;
use App\Utils\Swagger\User\User;
use Exception;
use Nelmio\ApiDocBundle\Annotation\Model;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use OpenApi\Attributes as OA;
use App\Service\OTP\OTPService;
use App\Utils\Swagger\Auth\OTPToken;


#[Route('/api',name: 'user_auth_api')]
class UserAuthenticationController extends AbstractController
{
    protected JWTManagementInterface $JWTManager;
    
    protected $passHasher;

    protected $userService;

    protected $OTPService;

    public function __construct(
        JWTManagementInterface $JWTManager,
        UserPasswordHasherInterface $hasher,
        UserService $userService,
        OTPService $OTPService
    )
    {
        $this->JWTManager = $JWTManager;

        $this->passHasher = $hasher;

        $this->userService = $userService;

        $this->OTPService = $OTPService;
    }

    #[OA\RequestBody(
        description: "Define New Variant",
        required: true,
        content: new OA\JsonContent(
            ref: new Model(type: User::class)
        )
    )]
    #[Route('/user/register', name: 'app_user_register',methods: ['POST'])]
    #[OA\Tag(name: 'Authentication')]
    public function create(Request $request, WalletServiceInterface $walletService): Response
    {
        try{
            $user = $this->userService->createFromArray($request->toArray());

            $token = $this->JWTManager->getTokenUser($user);
            $walletService->create($user);

            return new JsonResponse($token);
        }catch(\Exception $e){
            return $this->json($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    #[OA\Response(
        response: Response::HTTP_BAD_REQUEST,
        description: 'bad request',
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'you logged out',
    )]
    #[Route('/user/logout', name: 'app_user_logout',methods: ['GET'])]
    #[OA\Tag(name: 'Authentication')]
    public function logout(): Response
    {
        try {
            $this->JWTManager->invalidateToken();
            return $this->json([
                'message' => 'you logged out',
                'status' => Response::HTTP_OK
            ]);
        } catch (\Throwable $exception) {
            return $this->json(json_decode($exception->getMessage()), Response::HTTP_BAD_REQUEST);
        }

    }

    #[Route('/user/login', name: 'app_user_login',methods: ['POST'])]
    #[OA\Response(
        response: 200,
        description: 'Returns the token and refresh token of an user',
    )]
    #[OA\Response(
        response: 401,
        description: 'Invalid credentials',
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid Request',
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: new Model(type: LoginDto::class)
        )
    )]
    #[OA\Tag(name: 'Authentication')]
    public function login(Request $request,UserRepository $repository,ValidatorInterface $validator): Response
    {
        try{
            $arrayRequest = $request->toArray();
            (new LoginDTO($arrayRequest,$validator))->doValidate();
            if ($user = $repository->findOneBy(['phoneNumber'=>$arrayRequest['username']]))
            {
                $this->JWTManager->checkIfPasswordIsValid($user,$request);
                $token = $this->JWTManager->getTokenUser($user);
                return new JsonResponse($token);
            } else {
                return $this->json([
                    'status'=>401,
                    'message'=>'Invalid credentials'
                ]);
            }
        } catch(Exception $e) {
            return $this->json(json_decode($e->getMessage()), Response::HTTP_BAD_REQUEST);
        }
    }

    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'password changed successfully',
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid Request',
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: new Model(type: User::class,groups: ['user.pass'])
        )
    )]
    #[Route('/user/new-password', name: 'app_user_new_password',methods: ['POST'])]
    #[OA\Tag(name: 'Authentication')]
    public function newPassword(Request $request): Response
    {
        $body = $request->toArray();
        $user = $this->JWTManager->authenticatedUser();
        try{
            $userId = $this->userService->getUserBy(['phoneNumber' => $user->getUserIdentifier()])->getId();
            if(! array_key_exists('password',$body))throw new Exception("Password field is empty");
            $this->userService->updatePasswordById($userId,$body['password']);
            $this->JWTManager->invalidateToken();
            return $this->json(
                ['message'=>'password changed successfully'],
                status: 200
            );
        } catch (Exception $e){
            return $this->json($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    #[OA\Response(
        response: Response::HTTP_BAD_REQUEST,
        description: 'bad request',
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'phone number changed successfully',
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: new Model(type: User::class,groups: ['user.userName'])
        )
    )]
    #[OA\Tag(name: 'Authentication')]
    #[Route('/user/new-phone-number', name: 'app_user_new_phone_number',methods: ['POST'])]
    public function newUserName(Request $request): Response
    {
        $body = $request->toArray();
        $user = $this->JWTManager->authenticatedUser();
        try{
            $userId = $this->userService->getUserBy(['phoneNumber' => $user->getUserIdentifier()])->getId();
            if(! array_key_exists('phone number',$body))throw new Exception("phone number field is empty");
            $this->userService->updatePhoneNumberById($userId,$body['phone number']);
            $this->JWTManager->invalidateToken();
            return $this->json(
                ['message'=>'phone number changed successfully'],
                status: 200
            );
        } catch (Exception $e){
            return $this->json($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/user/validate-phone', name: 'app_user_validate_phone',methods: ['GET'])]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Sends an OTP to the user phone number',
    )]
    #[OA\Response(
        response: Response::HTTP_UNAUTHORIZED,
        description: 'Invalid credentials',
    )]
    #[OA\Response(
        response: Response::HTTP_BAD_REQUEST,
        description: 'Invalid Request',
    )]
    #[OA\Tag(name: 'Authentication')]
    public function validatePhone(): Response
    {
        try{
            $user = $this->JWTManager->authenticatedUser();
            $this->OTPService->requestToken($user);
            $response = $this->json(
                ['message'=>'token sent successfully'],
                status: Response::HTTP_OK
            );
            return $response;
        } catch (Exception $e){
            return $this->json($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/user/validate-token', name: 'app_user_validate_token',methods: ['POST'])]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Validates the token sent to the user phone number',
    )]
    #[OA\Response(
        response: Response::HTTP_UNAUTHORIZED,
        description: 'Invalid credentials',
    )]
    #[OA\Response(
        response: Response::HTTP_BAD_REQUEST,
        description: 'Invalid Request',
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            ref: new Model(type: OTPToken::class)
        )
    )]
    #[OA\Tag(name: 'Authentication')]
    public function validateToken(Request $request): Response
    {
        try{
            $user = $this->JWTManager->authenticatedUser();
            $body = $request->toArray();
            if(! array_key_exists('token',$body))
                throw new Exception("token field is empty");
            $this->OTPService->verifyToken($user,$body['token']);
            $response = $this->json(
                ['message'=>'token validated successfully'],
                status: Response::HTTP_OK
            );
            return $response;
        } catch (Exception $e){
            return $this->json($e->getMessage(), Response::HTTP_BAD_REQUEST);
        }
    }

//    #[Route('/gogo/{id}', name: 'gogo',methods: ['GET'])]
//    public function update(UserService $userService, EntityManagerInterface $em, $id): Response
//    {
//        try{
//            $seller = $em->getRepository(Seller::class)->find($id);
////            $userService->updatePhoneNumberById($id,'+989666665676');
//            dd($seller);
//        }catch(Exception $e){
//            return $this->json(json_decode($e), Response::HTTP_OK);
//        }
//    }
//
//    #[Route('/gogol/{id}', name: 'gogol',methods: ['GET'])]
//    public function _update(CacheEntityManager $em, int $id, CacheInterface $cache): Response
//    {
//        try{
//            $repo = $em->getRepository(Seller::class);
//            $seller = $repo->find($id);
////            $repo->deleteAllFromCache();
////            $userService->updatePhoneNumberById($id,'+989666665676');
//            dd($seller);
//        }catch(Exception $e){
//            return $this->json(json_decode($e), Response::HTTP_OK);
//        }
//    }
}
