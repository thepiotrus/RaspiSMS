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
     * Page des groups.
     */
    class Group extends \descartes\Controller
    {
        private $internal_group;
        private $internal_contact;
        private $internal_event;

        /**
         * Cette fonction est appelée avant toute les autres :
         * Elle vérifie que l'utilisateur est bien connecté.
         *
         * @return void;
         */
        public function __construct()
        {
            $bdd = \descartes\Model::_connect(DATABASE_HOST, DATABASE_NAME, DATABASE_USER, DATABASE_PASSWORD);

            $this->internal_group = new \controllers\internals\Group($bdd);
            $this->internal_contact = new \controllers\internals\Contact($bdd);
            $this->internal_event = new \controllers\internals\Event($bdd);

            \controllers\internals\Tool::verifyconnect();
        }

        /**
         * Cette fonction retourne tous les groups, sous forme d'un tableau permettant l'administration de ces groups.
         *
         * @param mixed $page
         */
        public function list($page = 0)
        {
            $page = (int) $page;
            $groups = $this->internal_group->list(25, $page);

            foreach ($groups as $key => $group)
            {
                $contacts = $this->internal_group->get_contacts($group['id']);
                $groups[$key]['nb_contacts'] = \count($contacts);
            }

            $this->render('group/list', ['groups' => $groups]);
        }

        /**
         * Cette fonction va supprimer une liste de groups.
         *
         * @param array int $_GET['ids'] : Les id des groups à supprimer
         * @param mixed     $csrf
         *
         * @return boolean;
         */
        public function delete($csrf)
        {
            if (!$this->verify_csrf($csrf))
            {
                \FlashMessage\FlashMessage::push('danger', 'Jeton CSRF invalid !');

                return $this->redirect(\descartes\Router::url('Group', 'list'));
            }

            $ids = $_GET['ids'] ?? [];
            $this->internal_group->delete($ids);

            return $this->redirect(\descartes\Router::url('Group', 'list'));
        }

        /**
         * Cette fonction retourne la page d'ajout d'un group.
         */
        public function add()
        {
            $this->render('group/add');
        }

        /**
         * Cette fonction retourne la page d'édition des groups.
         *
         * @param int... $ids : Les id des groups à supprimer
         */
        public function edit()
        {
            $ids = $_GET['ids'] ?? [];

            $groups = $this->internal_group->gets($ids);

            foreach ($groups as $key => $group)
            {
                $groups[$key]['contacts'] = $this->internal_group->get_contacts($group['id']);
            }

            $this->render('group/edit', [
                'groups' => $groups,
            ]);
        }

        /**
         * Cette fonction insert un nouveau group.
         *
         * @param $csrf : Le jeton CSRF
         * @param string $_POST['name']     : Le nom du group
         * @param array  $_POST['contacts'] : Les ids des contacts à mettre dans le group
         */
        public function create($csrf)
        {
            if (!$this->verify_csrf($csrf))
            {
                \FlashMessage\FlashMessage::push('danger', 'Jeton CSRF invalid !');

                return $this->redirect(\descartes\Router::url('Group', 'add'));
            }

            $name = $_POST['name'] ?? false;
            $contacts_ids = $_POST['contacts'] ?? false;

            if (!$name || !$contacts_ids)
            {
                \FlashMessage\FlashMessage::push('danger', 'Des champs sont manquants !');

                return $this->redirect(\descartes\Router::url('Group', 'add'));
            }

            $id_group = $this->internal_group->create($name, $contacts_ids);
            if (!$id_group)
            {
                \FlashMessage\FlashMessage::push('danger', 'Impossible de créer ce groupe.');

                return $this->redirect(\descartes\Router::url('Group', 'add'));
            }

            \FlashMessage\FlashMessage::push('success', 'Le groupe a bien été créé.');

            return $this->redirect(\descartes\Router::url('Group', 'list'));
        }

        /**
         * Cette fonction met à jour une group.
         *
         * @param $csrf : Le jeton CSRF
         * @param array $_POST['groups'] : Un tableau des groups avec leur nouvelle valeurs & une entrée 'contacts_id' avec les ids des contacts pour chaque group
         *
         * @return boolean;
         */
        public function update($csrf)
        {
            if (!$this->verify_csrf($csrf))
            {
                \FlashMessage\FlashMessage::push('danger', 'Jeton CSRF invalid !');

                return $this->redirect(\descartes\Router::url('Group', 'list'));
            }

            $groups = $_POST['groups'] ?? [];

            $nb_groups_update = 0;
            foreach ($groups as $id => $group)
            {
                $nb_groups_update += (int) $this->internal_group->update($id, $group['name'], $group['contacts_ids']);
            }

            if ($nb_groups_update !== \count($groups))
            {
                \FlashMessage\FlashMessage::push('danger', 'Certains groupes n\'ont pas pu êtres mis à jour.');

                return $this->redirect(\descartes\Router::url('Group', 'list'));
            }

            \FlashMessage\FlashMessage::push('success', 'Tous les groupes ont été modifiés avec succès.');

            return $this->redirect(\descartes\Router::url('Group', 'list'));
        }

        /**
         * Cette fonction retourne la liste des groups sous forme JSON.
         */
        public function json_list()
        {
            header('Content-Type: application/json');
            echo json_encode($this->internal_group->list());
        }
    }