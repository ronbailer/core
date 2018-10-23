<?php
/**
 * @author Sujith Haridasan <sharidasan@owncloud.com>
 *
 * @copyright Copyright (c) 2018, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OC\User\Service;

use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Mail\IMailer;
use OCP\Security\ISecureRandom;
use OCP\User\Exceptions\CannotCreateUserException;
use OCP\User\Exceptions\InvalidEmailException;
use OCP\User\Exceptions\UserAlreadyExistsException;

class CreateUser {
	/** @var IUserSession */
	private $userSession;
	/** @var IGroupManager */
	private $groupManager;
	/** @var IUserManager */
	private $userManager;
	/** @var IMailer */
	private $mailer;
	/** @var ISecureRandom */
	private $secureRandom;
	/** @var ILogger */
	private $logger;
	/** @var UserSendMail */
	private $userSendMail;
	/** @var CreatePassword */
	private $createPassword;

	/**
	 * CreateUser constructor.
	 *
	 * @param IUserSession $userSession
	 * @param IGroupManager $groupManager
	 * @param IUserManager $userManager
	 * @param IMailer $mailer
	 * @param ISecureRandom $secureRandom
	 * @param ILogger $logger
	 * @param UserSendMail $userSendMail
	 * @param CreatePassword $createPassword
	 */
	public function __construct(IUserSession $userSession,
								IGroupManager $groupManager,
								IUserManager $userManager,
								IMailer $mailer,
								ISecureRandom $secureRandom,
								ILogger $logger,
								UserSendMail $userSendMail,
								CreatePassword $createPassword) {
		$this->userSession = $userSession;
		$this->groupManager = $groupManager;
		$this->userManager = $userManager;
		$this->mailer = $mailer;
		$this->secureRandom = $secureRandom;
		$this->logger = $logger;
		$this->userSendMail = $userSendMail;
		$this->createPassword = $createPassword;
	}

	/**
	 * @param $username
	 * @param $password
	 * @param array $groups
	 * @param string $email
	 * @return bool|\OCP\IUser
	 * @throws CannotCreateUserException
	 * @throws InvalidEmailException
	 * @throws UserAlreadyExistsException
	 */
	public function createUser($username, $password, array $groups= [], $email='') {
		if ($email !== '' && !$this->mailer->validateMailAddress($email)) {
			throw new InvalidEmailException("Invalid mail address");
		}

		$currentUser = $this->userSession->getUser();

		if (!$this->isAdmin()) {
			if (!empty($groups)) {
				foreach ($groups as $key => $group) {
					$groupObject = $this->groupManager->get($group);
					if ($groupObject === null) {
						unset($groups[$key]);
						continue;
					}

					if (!$this->groupManager->getSubAdmin()->isSubAdminofGroup($currentUser, $groupObject)) {
						unset($groups[$key]);
					}
				}
			}

			if (empty($groups)) {
				$groups = $this->groupManager->getSubAdmin()->getSubAdminsGroups($currentUser);
				// New class returns IGroup[] so convert back
				$gids = [];
				foreach ($groups as $group) {
					$gids[] = $group->getGID();
				}
				$groups = $gids;
			}
		}

		if ($this->userManager->userExists($username)) {
			throw new UserAlreadyExistsException('A user with that name already exists.');
		}

		try {
			if (($password === '') && ($email !== '')) {
				/**
				 * Generate a random password as we are going to have this
				 * use one time. The new user has to reset it using the link
				 * from email.
				 */
				$password = $this->createPassword->createPassword();
			}
			$user = $this->userManager->createUser($username, $password);
		} catch (\Exception $exception) {
			throw new CannotCreateUserException("Unable to create user due to exception: {$exception->getMessage()}");
		}

		if ($user === false) {
			throw new CannotCreateUserException('Unable to create user.');
		}

		if ($groups !== null) {
			foreach ($groups as $groupName) {
				if ($groupName !== null) {
					$group = $this->groupManager->get($groupName);

					if (empty($group)) {
						$group = $this->groupManager->createGroup($groupName);
					}
					$group->addUser($user);
					$this->logger->info('Added userid ' . $user->getUID() . ' to group ' . $group->getGID());
				}
			}
		}
		/**
		 * Send new user mail only if a mail is set
		 */
		if ($email !== '') {
			$user->setEMailAddress($email);
			try {
				$this->userSendMail->generateTokenAndSendMail($username, $email);
			} catch (\Exception $e) {
				$this->logger->error("Can't send new user mail to $email: " . $e->getMessage(), ['app' => 'settings']);
			}
		}

		return $user;
	}

	private function isAdmin() {
		// Check if current user (active and not in incognito mode)
		// is an admin
		$activeUser = $this->userSession->getUser();
		if ($activeUser !== null) {
			return $this->groupManager->isAdmin($activeUser->getUID());
		}
		// Check if it is triggered from command line
		$cli = $this->triggeredFromCommandLine();
		if ($cli === true) {
			return true;
		}
		return false;
	}

	/**
	 * Check if action is triggered from command line
	 * @return bool
	 */
	private function triggeredFromCommandLine() {
		return \OC::$CLI;
	}
}
