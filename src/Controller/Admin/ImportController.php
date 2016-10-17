<?php
/**
 * Pi Engine (http://pialog.org)
 *
 * @link            http://code.pialog.org for the Pi Engine source repository
 * @copyright       Copyright (c) Pi Engine http://pialog.org
 * @license         http://pialog.org/license.txt New BSD License
 */

/**
 * @author Hossein Azizabadi <azizabadi@faragostaresh.com>
 */
namespace Module\Userimportexport\Controller\Admin;

use Pi;
use Pi\Mvc\Controller\ActionController;

class ImportController extends ActionController
{
    public function indexAction()
    {
        $addUser = $this->params('addUser');
        $file = Pi::path('upload/userimportexport/user.csv');
        $users = array();
        if (Pi::service('file')->exists($file)) {
            // Set
            $message = sprintf(__('You can import this information from %s'), $file);
            $countOfUser = 0;
            // Get user meta
            $meta = Pi::registry('field', 'user')->read();
            // Set file users to array
            // from : https://secure.php.net/manual/en/function.fgetcsv.php
            $userData = array();
            $row = 1;
            if (($handle = fopen($file, "r")) !== false) {
                while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                    $num = count($data);
                    $i = 1;
                    for ($c = 0; $c < $num; $c++) {
                        $userData[$row][$i] = $data[$c];
                        $i++;
                    }
                    $row++;
                }
                fclose($handle);
            }
            // Make user field list
            $fieldList = array_shift($userData);
            // Make user array and import to DB
            foreach ($userData as $userId => $userInfo) {
                // Set user values
                foreach ($userInfo as $key => $field) {
                    if (isset($meta[$fieldList[$key]])) {
                        $users[$userId][$fieldList[$key]] = $field;
                    }
                }
                $users[$userId]['last_modified'] = time();
                $users[$userId]['ip_register']   = Pi::user()->getIp();
                // Check allow add user by admin
                if ($addUser == 'OK') {
                    // Check field list
                    $mainFieldList = array('identity', 'identity', 'email', 'name');
                    foreach ($mainFieldList as $mainField) {
                        if (!in_array($mainField, $fieldList)) {
                            $url = array('action' => 'index');
                            $this->jump($url, sprintf(__('%s field not set'), $mainField), 'error');
                        }
                    }
                    // Add user
                    $uid = Pi::api('user', 'user')->addUser($users[$userId]);
                    // Check user add or not
                    if ($uid) {
                        // Set user role
                        Pi::api('user', 'user')->setRole($uid, 'member');
                        // Active user
                        $status = Pi::api('user', 'user')->activateUser($uid);
                        if ($status) {
                            // Target activate user event
                            Pi::service('event')->trigger('user_activate', $uid);
                            // Update count
                            $countOfUser++;
                        }
                    }
                }
            }
            // Back to index if add user if OK
            if ($addUser == 'OK') {
                $url = array('action' => 'index');
                if ($countOfUser > 0) {
                    $this->jump($url, sprintf(__('%s user added'), $countOfUser));
                } else {
                    $this->jump($url, __('No user added !'), 'error');
                }
            }
        } else {
            $message = sprintf(__('User.csv not exist on %s'), $file);
        }

        // Set template
        $this->view()->setTemplate('import-index');
        $this->view()->assign('message', $message);
        $this->view()->assign('file', $file);
        $this->view()->assign('users', $users);
        $this->view()->assign('f', Pi::registry('field', 'user')->read());
    }
}