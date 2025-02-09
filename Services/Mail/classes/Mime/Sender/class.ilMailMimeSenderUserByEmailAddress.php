<?php

/**
 * This file is part of ILIAS, a powerful learning management system
 * published by ILIAS open source e-Learning e.V.
 *
 * ILIAS is licensed with the GPL-3.0,
 * see https://www.gnu.org/licenses/gpl-3.0.en.html
 * You should have received a copy of said license along with the
 * source code, too.
 *
 * If this is not the case or you just want to try ILIAS, you'll find
 * us at:
 * https://www.ilias.de
 * https://github.com/ILIAS-eLearning
 *
 *********************************************************************/

declare(strict_types=1);

/**
 * Class ilMailMimeSenderUserByEmailAddress
 * @author Michael Jansen <mjansen@databay.de>
 */
class ilMailMimeSenderUserByEmailAddress extends ilMailMimeSenderUser
{
    public function __construct(ilSetting $settings, string $emailAddress, ilMustacheFactory $mustache_factory)
    {
        $user = new ilObjUser();
        $user->setEmail($emailAddress);

        parent::__construct($settings, $user, $mustache_factory);
    }
}
