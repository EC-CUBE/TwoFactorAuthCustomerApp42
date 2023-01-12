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

namespace Plugin\TwoFactorAuthCustomerApp42;

use Doctrine\ORM\EntityManagerInterface;
use Eccube\Entity\Layout;
use Eccube\Entity\Page;
use Eccube\Entity\PageLayout;
use Eccube\Plugin\AbstractPluginManager;
use Eccube\Repository\LayoutRepository;
use Eccube\Repository\PageLayoutRepository;
use Eccube\Repository\PageRepository;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Plugin\TwoFactorAuthCustomer42\Entity\TwoFactorAuthConfig;
use Plugin\TwoFactorAuthCustomer42\Entity\TwoFactorAuthType;

/**
 * Class PluginManager.
 */
class PluginManager extends AbstractPluginManager
{
    // 設定対象ページ情報
    private $pages = [
        ['plg_customer_2fa_app_create', 'アプリ認証初期設定・トークン入力', 'TwoFactorAuthCustomer42/Resource/template/default/tfa/app/register'],
        ['plg_customer_2fa_app_challenge', 'アプリ認証トークン入力', 'TwoFactorAuthCustomer42/Resource/template/default/tfa/app/challenge'],
    ];

    /**
     * @param array $meta
     * @param ContainerInterface $container
     */
    public function enable(array $meta, ContainerInterface $container)
    {
        $em = $container->get('doctrine')->getManager();

        $this->createConfig($em);

        // twigファイルを追加
        $this->copyTwigFiles($container);

        // ページ登録
        $this->createPages($em);
    }

    /**
     * @param array $meta
     * @param ContainerInterface $container
     */
    public function disable(array $meta, ContainerInterface $container)
    {

    }

    /**
     * @param array $meta
     * @param ContainerInterface $container
     */
    public function uninstall(array $meta, ContainerInterface $container)
    {
        $em = $container->get('doctrine')->getManager();

        // twigファイルを削除
        $this->removeTwigFiles($container);

        // ページ削除
        $this->removePages($em);
    }

    /**
     * Twigファイルの登録
     * 
     * @param ContainerInterface $container
     */
    protected function copyTwigFiles(ContainerInterface $container)
    {
        // テンプレートファイルコピー
        $templatePath = $container->getParameter('eccube_theme_front_dir')
            .'/TwoFactorAuthCustomerApp42/Resource/template/default';
        $fs = new Filesystem();
        if ($fs->exists($templatePath)) {
            return;
        }
        $fs->mkdir($templatePath);
        $fs->mirror(__DIR__.'/Resource/template/default', $templatePath);
    }

    /** 
     * ページ情報の登録
     * 
     * @param EntityManagerInterface $em
     */
    protected function createPages(EntityManagerInterface $em)
    {
        foreach ($this->pages as $p) {
            $Page = $em->getRepository(Page::class)->findOneBy(['url' => $p[0]]);
            if (!$Page) {
                /** @var \Eccube\Entity\Page $Page */
                $Page = $em->getRepository(Page::class)->newPage();
                $Page->setEditType(Page::EDIT_TYPE_DEFAULT);
                $Page->setUrl($p[0]);
                $Page->setName($p[1]);
                $Page->setFileName($p[2]);
                $Page->setMetaRobots('noindex');
    
                $em->persist($Page);
                $em->flush();
    
                $Layout = $em->getRepository(Layout::class)->find(Layout::DEFAULT_LAYOUT_UNDERLAYER_PAGE);
                $PageLayout = new PageLayout();
                $PageLayout->setPage($Page)
                    ->setPageId($Page->getId())
                    ->setLayout($Layout)
                    ->setLayoutId($Layout->getId())
                    ->setSortNo(0);
                $em->persist($PageLayout);
                $em->flush();
            }
        }
    }

    /**
     * Twigファイルの削除
     * 
     * @param ContainerInterface $container
     */
    protected function removeTwigFiles(ContainerInterface $container)
    {
        $templatePath = $container->getParameter('eccube_theme_front_dir')
            .'/TwoFactorAuthCustomerApp42';
        $fs = new Filesystem();
        $fs->remove($templatePath);
    }

    /** 
     * ページ情報の削除
     * 
     * @param EntityManagerInterface $em
     */
    protected function removePages(EntityManagerInterface $em)
    {
        foreach ($this->pages as $p) {
            $Page = $em->getRepository(Page::class)->findOneBy(['url' => $p[0]]);
            if (!$Page) {
                $Layout = $em->getRepository(Layout::class)->find(Layout::DEFAULT_LAYOUT_UNDERLAYER_PAGE);
                $PageLayout = $em->getRepository(PageLayout::class)->findOneBy(['Page' => $Page, 'Layout' => $Layout]);

                $em->remove($PageLayout);
                $em->remove($Page);
                $em->flush();
            }
        }
    }

    /**
     * 設定の登録.
     *
     * @param EntityManagerInterface $em
     */
    protected function createConfig(EntityManagerInterface $em)
    {
        $TwoFactorAuthType = $em->getRepository(TwoFactorAuthType::class)->findBy([ 'name' => 'APP' ]);
        if (!$TwoFactorAuthType) {
            // レコードを保存
            $TwoFactorAuthType = new TwoFactorAuthType();

            $TwoFactorAuthType
                ->setName('APP')
                ->setRoute('plg_customer_2fa_app_create')
            ;

            $em->persist($TwoFactorAuthType);
        }

        // 除外ルートの登録
        $TwoFactorAuthConfig = $em->find(TwoFactorAuthConfig::class, 1);
        foreach ($this->pages as $p) {
            $TwoFactorAuthConfig->addExcludeRoute($p[0]);
        }
        $em->persist($TwoFactorAuthConfig);
        $em->flush();

        return;
    }    

}