<?php

/*
 * This file is part of PHP CS Fixer.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *     Dariusz Rumiński <dariusz.ruminski@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace controllers\publics;

    /**
     * Page des smsstops.
     */
    class SmsStop extends \descartes\Controller
    {
        private $internal_sms_stop;

        /**
         * Cette fonction est appelée avant toute les autres :
         * Elle vérifie que l'utilisateur est bien connecté.
         *
         * @return void;
         */
        public function __construct()
        {
            $bdd = \descartes\Model::_connect(DATABASE_HOST, DATABASE_NAME, DATABASE_USER, DATABASE_PASSWORD);
            $this->internal_sms_stop = new \controllers\internals\SmsStop($bdd);

            \controllers\internals\Tool::verifyconnect();
        }

        /**
         * Cette fonction retourne tous les smsstops, sous forme d'un tableau permettant l'administration de ces smsstops.
         *
         * @param mixed $page
         */
        public function list($page = 0)
        {
            $page = (int) $page;
            $limit = 25;
            $smsstops = $this->internal_sms_stop->list($limit, $page);
            $this->render('smsstop/list', ['page' => $page, 'smsstops' => $smsstops, 'limit' => $limit, 'nb_results' => \count($smsstops)]);
        }

        /**
         * Cette fonction va supprimer une liste de smsstops.
         *
         * @param array int $_GET['ids'] : Les id des smsstopes à supprimer
         * @param mixed     $csrf
         *
         * @return boolean;
         */
        public function delete($csrf)
        {
            if (!$this->verify_csrf($csrf))
            {
                \FlashMessage\FlashMessage::push('danger', 'Jeton CSRF invalid !');

                return $this->redirect(\descartes\Router::url('SmsStop', 'list'));
            }

            if (!\controllers\internals\Tool::is_admin())
            {
                \FlashMessage\FlashMessage::push('danger', 'Vous devez être administrateur pour pouvoir supprimer un "STOP Sms" !');

                return $this->redirect(\descartes\Router::url('SmsStop', 'list'));
            }

            $ids = $_GET['ids'] ?? [];
            foreach ($ids as $id)
            {
                $this->internal_sms_stop->delete($id);
            }

            return $this->redirect(\descartes\Router::url('SmsStop', 'list'));
        }
    }