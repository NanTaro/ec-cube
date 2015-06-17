<?php
/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) 2000-2015 LOCKON CO.,LTD. All Rights Reserved.
 *
 * http://www.lockon.co.jp/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */


namespace Eccube\Controller\Admin\Content;

use Eccube\Application;
use Symfony\Component\Filesystem\Filesystem;

class PageController
{
    public function index(Application $app, $id = null)
    {
        $DeviceType = $app['eccube.repository.master.device_type']
            ->find(\Eccube\Entity\Master\DeviceType::DEVICE_TYPE_PC);

        $PageLayout = $app['eccube.repository.page_layout']
            ->findOrCreate($id, $DeviceType);

        $builder = $app['form.factory']->createBuilder('main_edit');

        $tpl_data = '';
        $editable = true;
        // 更新時
        if ($id) {
            // 編集不可ページはURL、ページ名、ファイル名を保持
            if ($PageLayout->getEditFlg() == 2) {
                $editable = false;
                $previous_url = $PageLayout->getUrl();
                $previous_filename = $PageLayout->getFilename();
                $previous_name = $PageLayout->getName();
            }
            // テンプレートファイルの取得
            $file = $app['eccube.repository.page_layout']
                ->getTemplateFile($PageLayout->getFilename(), $DeviceType, $editable);
            $tpl_data = $file['tpl_data'];
        }

        $form = $builder->getForm();
        $form->setData($PageLayout);
        $form->get('tpl_data')->setData($tpl_data);

        if ($app['request']->getMethod() === 'POST') {
            $form->handleRequest($app['request']);
            if ($form->isValid()) {
                $PageLayout = $form->getData();
                $PageLayout->setUrl($form->get('filename')->getData());

                if (!$editable) {
                    $PageLayout->setUrl($previous_url);
                    $PageLayout->setFilename($previous_filename);
                    $PageLayout->setName($previous_name);
                }
                // DB登録
                $app['orm.em']->persist($PageLayout);
                $app['orm.em']->flush();
                // ファイル生成・更新
                $templatePath = $app['eccube.repository.page_layout']->getTemplatePath($DeviceType, $editable);
                $filePath = $templatePath . $PageLayout->getFilename() . '.twig';
                $fs = new Filesystem();
                $fs->dumpFile($filePath, $form->get('tpl_data')->getData());
                // TODO:ルーティングの追加
                $app['session']->getFlashBag()->add('page.complete', 'admin.register.complete');

                return $app->redirect($app['url_generator']->generate('admin_content_page'));
            }
        }

        // 登録されているページ一覧の取得
        $PageLayouts = $app['eccube.repository.page_layout']->getPageList($DeviceType);

        return $app->render('Content/page.twig', array(
            'form' => $form->createView(),
            'PageLayouts' => $PageLayouts,
            'page_id' => $id,
            'editable' => $editable,
        ));
    }

    public function delete(Application $app, $id = null)
    {
        $DeviceType = $app['eccube.repository.master.device_type']
            ->find(\Eccube\Entity\Master\DeviceType::DEVICE_TYPE_PC);

        $PageLayout = $app['eccube.repository.page_layout']->findOrCreate($id, $DeviceType);

        // ユーザーが作ったページのみ削除する
        if ($PageLayout->getEditFlg() == null) {
            $templatePath = $app['eccube.repository.page_layout']
                ->getTemplatePath($DeviceType, true);
            $file = $templatePath . $PageLayout->getFileName() . '.twig';
            $fs = new Filesystem();
            if ($fs->exists($file)) {
                $fs->remove($file);
            }
            $app['orm.em']->remove($PageLayout);
            $app['orm.em']->flush();
        }

        return $app->redirect($app->url('admin_content_page'));
    }
}
