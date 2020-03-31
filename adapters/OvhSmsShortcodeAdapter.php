<?php

/*
 * This file is part of RaspiSMS.
 *
 * (c) Pierre-Lin Bonnemaison <plebwebsas@gmail.com>
 *
 * This source file is subject to the GPL-3.0 license that is bundled
 * with this source code in the file LICENSE.
 */

namespace adapters;

    use Ovh\Api;

    /**
     * OVH SMS service with a shortcode allowing responses
     */
    class OvhSmsShortcodeAdapter implements AdapterInterface
    {
        /**
         * Datas used to configure interaction with the implemented service. (e.g : Api credentials, ports numbers, etc.).
         */
        private $datas;

        /**
         * OVH Api instance.
         */
        private $api;

        /**
         * Number formated to be compatible with http query according to the ovh way.
         */
        private $formatted_number;

        /**
         * Adapter constructor, called when instanciated by RaspiSMS.
         *
         * @param string      $number : Phone number the adapter is used for
         * @param json string $datas  : JSON string of the datas to configure interaction with the implemented service
         */
        public function __construct(string $datas)
        {
            $this->datas = json_decode($datas, true);

            $this->api = new Api(
                $this->datas['app_key'],
                $this->datas['app_secret'],
                'ovh-eu',
                $this->datas['consumer_key']
            );
        }

        /**
         * Classname of the adapter.
         */
        public static function meta_classname(): string
        {
            return __CLASS__;
        }

        /**
         * Name of the adapter.
         * It should probably be the name of the service it adapt (e.g : Gammu SMSD, OVH SMS, SIM800L, etc.).
         */
        public static function meta_name(): string
        {
            return 'OVH SMS Shortcode';
        }

        /**
         * Description of the adapter.
         * A short description of the service the adapter implements.
         */
        public static function meta_description(): string
        {
            $callback = \descartes\Router::url('Callback', 'update_sended_status', ['adapter_name' => self::meta_name()], ['api_key' => $_SESSION['user']['api_key'] ?? '<your_api_key>']);
            $generate_credentials_url = 'https://eu.api.ovh.com/createToken/index.cgi?GET=/sms&GET=/sms/*&POST=/sms/*&PUT=/sms/*&DELETE=/sms/*&';

            return '
                Solution de SMS proposé par le groupe <a target="_blank" href="https://www.ovhtelecom.fr/sms/">OVH</a>. Pour générer les clefs API OVH, <a target="_blank" href="' . $generate_credentials_url . '">cliquez ici.</a>
                <br/>
                <br/>
                <div class="alert alert-info">Adresse URL de callback de changement d\'état : <b>' . $callback . '</b></div>
            ';
        }

        /**
         * List of entries we want in datas for the adapter.
         *
         * @return array : Every line is a field as an array with keys : name, title, description, required
         */
        public static function meta_datas_fields(): array
        {
            return [
                [
                    'name' => 'service_name',
                    'title' => 'Service Name',
                    'description' => 'Service Name de votre service SMS chez OVH. Il s\'agit du nom associé à votre service SMS dans la console OVH, probablement quelque chose comme "sms-xxxxx-1" ou "xxxx" est votre identifiant client OVH.',
                    'required' => true,
                ],
                [
                    'name' => 'sender',
                    'title' => 'Nom de l\'expéditeur',
                    'description' => 'Nom de l\'expéditeur à afficher à la place du numéro (11 caractères max).<br/>
                                      <b>Laissez vide pour ne pas utiliser d\'expéditeur nommé.</b><br/>
                                      Le nom doit avoir été validé au préallable. <b>Si vous utilisez un expéditeur nommé, le destinataire ne pourra pas répondre.</b>',
                    'required' => false,
                ],
                [
                    'name' => 'app_key',
                    'title' => 'Application Key',
                    'description' => 'Paramètre "Application Key" obtenu lors de la génération de la clef API OVH.',
                    'required' => true,
                ],
                [
                    'name' => 'app_secret',
                    'title' => 'Application Secret',
                    'description' => 'Paramètre "Application Secret" obtenu lors de la génération de la clef API OVH.',
                    'required' => true,
                ],
                [
                    'name' => 'consumer_key',
                    'title' => 'Consumer Key',
                    'description' => 'Paramètre "Consumer Key" obtenu lors de la génération de la clef API OVH.',
                    'required' => true,
                ],
            ];
        }

        /**
         * Does the implemented service support reading smss.
         */
        public static function meta_support_read(): bool
        {
            return true;
        }

        /**
         * Does the implemented service support flash smss.
         */
        public static function meta_support_flash(): bool
        {
            return false;
        }

        /**
         * Does the implemented service support status change.
         */
        public static function meta_support_status_change(): bool
        {
            return true;
        }

        /**
         * Method called to send a SMS to a number.
         *
         * @param string $destination : Phone number to send the sms to
         * @param string $text        : Text of the SMS to send
         * @param bool   $flash       : Is the SMS a Flash SMS
         *
         * @return mixed Uid of the sended message if send, False else
         */
        public function send(string $destination, string $text, bool $flash = false)
        {
            try
            {
                $success = true;

                $endpoint = '/sms/' . $this->datas['service_name'] . '/jobs';
                $params = [
                    'message' => $text,
                    'receivers' => [$destination],
                    'senderForResponse' => true,
                ];

                if ($this->datas['sender'])
                {
                    $params['sender'] = $this->datas['sender'];
                    $params['senderForResponse'] = false;
                }

                $response = $this->api->post($endpoint, $params);

                $nb_invalid_receivers = \count(($response['invalidReceivers'] ?? []));
                if ($nb_invalid_receivers > 0)
                {
                    return false;
                }

                $uids = $response['ids'] ?? [];

                return $uids[0] ?? false;
            }
            catch (\Exception $e)
            {
                return false;
            }
        }

        /**
         * Method called to read SMSs of the number.
         *
         * @return array : Array of the sms reads
         */
        public function read(): array
        {
            try
            {
                $success = true;

                $endpoint = '/sms/' . $this->datas['service_name'] . '/incoming';
                $uids = $this->api->get($endpoint);

                if (!\is_array($uids) || !$uids)
                {
                    return [];
                }

                $received_smss = [];
                foreach ($uids as $uid)
                {
                    $endpoint = '/sms/' . $this->datas['service_name'] . '/incoming/' . $uid;
                    $sms_details = $this->api->get($endpoint);

                    if (!isset($sms_details['creationDatetime'], $sms_details['message'], $sms_details['sender']))
                    {
                        continue;
                    }

                    $received_smss[] = [
                        'at' => (new \DateTime($sms_details['creationDatetime']))->format('Y-m-d H:i:s'),
                        'text' => $sms_details['message'],
                        'origin' => $sms_details['sender'],
                    ];

                    //Remove the sms to prevent double reading as ovh do not offer a filter for unread messages only
                    $endpoint = '/sms/' . $this->datas['service_name'] . '/incoming/' . $uid;
                    $this->api->delete($endpoint);
                }

                return $received_smss;
            }
            catch (\Exception $e)
            {
                return [];
            }
        }

        /**
         * Method called to verify if the adapter is working correctly
         * should be use for exemple to verify that credentials and number are both valid.
         *
         * @return bool : False on error, true else
         */
        public function test(): bool
        {
            try
            {
                $success = true;
    
                if ($this->datas['sender'] && mb_strlen($this->datas['sender']))
                {
                    return false;
                }

                //Check service name
                $endpoint = '/sms/' . $this->datas['service_name'];
                $response = $this->api->get($endpoint);
                $success = $success && (bool) $response;

                return $success;
            }
            catch (\Exception $e)
            {
                return false;
            }
        }

        /**
         * Method called on reception of a status update notification for a SMS.
         *
         * @return mixed : False on error, else array ['uid' => uid of the sms, 'status' => New status of the sms ('unknown', 'delivered', 'failed')]
         */
        public static function status_change_callback()
        {
            $uid = $_GET['id'] ?? false;
            $dlr = $_GET['dlr'] ?? false;

            if (false === $uid || false === $dlr)
            {
                return false;
            }

            switch ($dlr)
            {
                case 1:
                    $status = 'delivered';

                    break;
                case 2:
                case 16:
                    $status = 'failed';

                    break;
                default:
                    $status = 'unknown';

                    break;
            }

            return ['uid' => $uid, 'status' => $status];
        }
    }
