<?php

namespace Plugin\TwoFactorAuthCustomerApp42\Controller;

use Plugin\TwoFactorAuthCustomer42\Controller\TwoFactorAuthCustomerController;
use Plugin\TwoFactorAuthCustomer42\Service\CustomerTwoFactorAuthService;
use Plugin\TwoFactorAuthCustomerApp42\Form\Type\TwoFactorAuthAppTypeCustomer;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use RobThree\Auth\TwoFactorAuth;


class TwoFactorAuthCustomerAppController extends TwoFactorAuthCustomerController
{
    /**
     * @var TwoFactorAuth
     */
    protected $tfa;

    /**
     * 初回APP認証画面.
     * @Route("/two_factor_auth/app/create", name="plg_customer_2fa_app_create", methods={"GET", "POST"})
     * @Template("TwoFactorAuthCustomerApp42/Resource/template/default/tfa/app/register.twig")
     */
    public function create(Request $request) 
    {
        if ($this->isTwoFactorAuthed()) {
            // 認証済み
            return $this->redirectToRoute($this->getCallbackRoute());
        }

        $this->tfa = new TwoFactorAuth();

        $error = null;
        /** @var Customer $Customer */
        $Customer = $this->getUser();

        $builder = $this->formFactory->createBuilder(TwoFactorAuthAppTypeCustomer::class);
        $form = null;
        $auth_key = null;

        if ('GET' === $request->getMethod()) {
            if ($Customer->getTwoFactorAuthSecret()) {
                // 既に二段階認証設定済み + APP認証設定済み(二回目以降)
                return [
                    'form' => $builder->getForm(),
                    'Customer' => $Customer,
                ];
            }
            $auth_key = $this->createSecret();
            $builder->get('auth_key')->setData($auth_key);
            $form = $builder->getForm();
        } elseif ('POST' === $request->getMethod()) {
            $form = $builder->getForm();
            $form->handleRequest($request);
            $auth_key = $form->get('auth_key')->getData();
            $token = $form->get('one_time_token')->getData();
            if ($form->isSubmitted() && $form->isValid()) {
                if ($this->verifyCode($auth_key, $token, 2)) {
                    // 二段階認証完了
                    $Customer->setTwoFactorAuth(true);
                    // 秘密鍵更新
                    $Customer->setTwoFactorAuthSecret($auth_key);
                    $this->entityManager->persist($Customer);
                    $this->entityManager->flush();
                    $this->addSuccess('front.2fa.complete_message');

                    $response = new RedirectResponse($this->generateUrl($this->getCallbackRoute()));
                    $response->headers->setCookie(
                        $this->customerTwoFactorAuthService->createAuthedCookie(
                            $Customer, 
                            $this->getCallbackRoute()
                    ));
                    return $response;
                } else {
                    $error = trans('front.2fa.onetime.invalid_message__reinput');
                }
            } else {
                $error = trans('front.2fa.onetime.invalid_message__reinput');
            }
        }

        return [
            'form' => $form->createView(),
            'Customer' => $Customer,
            'auth_key' => $auth_key,
            'error' => $error,
        ];
    }

    /**
     * APP認証画面.
     * @Route("/two_factor_auth/app/challenge", name="plg_customer_2fa_app_challenge", methods={"GET", "POST"})
     * @Template("TwoFactorAuthCustomerApp42/Resource/template/default/tfa/app/challenge.twig")
     */
    public function challenge(Request $request) 
    {
        if ($this->isTwoFactorAuthed()) {
            // 認証済み
            return $this->redirectToRoute($this->getCallbackRoute());
        }

        $this->tfa = new TwoFactorAuth();

        $error = null;
        /** @var Customer $Customer */
        $Customer = $this->getUser();

        if ($Customer->getTwoFactorAuthSecret() == null) {
            // APP認証設定まだ
            return $this->redirectToRoute('plg_customer_2fa_app_create');
        }

        $builder = $this->formFactory->createBuilder(TwoFactorAuthAppTypeCustomer::class);
        $builder->remove('auth_key');
        $form = $builder->getForm();

        if ('POST' === $request->getMethod()) {
            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                if ($this->verifyCode($Customer->getTwoFactorAuthSecret(), $form->get('one_time_token')->getData())) {
                    $response = new RedirectResponse($this->generateUrl($this->getCallbackRoute()));
                    $response->headers->setCookie(
                        $this->customerTwoFactorAuthService->createAuthedCookie(
                            $Customer, 
                            $this->getCallbackRoute()
                        )
                    );
                    return $response;
                } else {
                    $error = trans('front.2fa.onetime.invalid_message__reinput');
                }
            } else {
                $error = trans('front.2fa.onetime.invalid_message__reinput');
            }
        }

        return [
            'form' => $form->createView(),
            'error' => $error,
        ];
    }

    /**
     * 認証コードを取得.
     * 
     * @param string $authKey
     * @param string $token
     *
     * @return boolean
     */
    private function verifyCode($authKey, $token)
    {
        return $this->tfa->verifyCode($authKey, $token, 2);
    }

    /**
     * 秘密鍵生成.
     * 
     * @return string
     */
    private function createSecret()
    {
        return $this->tfa->createSecret();
    }
}
