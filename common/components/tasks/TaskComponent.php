<?php

namespace common\components\tasks;

use common\components\tasks\bigGame\BigGameConfirmProfile;
use common\components\tasks\bigGame\BigGameCreditPay;
use common\components\tasks\bigGame\BigGameCreditUpgrade;
use common\components\tasks\bigGame\BigGameDigiuInfo;
use common\components\tasks\bigGame\BigGameInterview;
use common\components\tasks\bigGame\BigGameInvoiceChild1000Month;
use common\components\tasks\bigGame\BigGameInvoicePackage;
use common\components\tasks\bigGame\BigGameInvoicePackageForNovichok;
use common\components\tasks\bigGame\BigGameNewChild1000;
use common\components\tasks\bigGame\BigGamePartnerNew10;
use common\components\tasks\bigGame\BigGameRegistrationLK;
use common\components\tasks\bigGame\BigGameVideoRolik;
use common\components\tasks\bigGame\BigGameWriteTg;
use common\components\tasks\other\ActivatedPartnerProgram;
use common\components\tasks\other\InvoiceBasketNovichokRwa10;
use common\components\tasks\other\InvoiceBasketNovichokRwa50;
use common\components\tasks\other\InvoiceBasketSpecPackage10;
use common\components\tasks\other\InvoiceCredit;
use common\components\tasks\other\InvoiceCreditDouble;
use common\components\tasks\other\InvoiceCreditFinished;
use common\components\tasks\other\InvoiceCreditFinishedFast;
use common\components\tasks\other\InvoiceCreditTriple;
use common\components\tasks\other\InvoiceNewPackageNotCredit;
use common\components\tasks\other\InvoicePrepayments;
use common\components\tasks\other\OpenContribution;
use common\components\tasks\other\Partner5PackageFor10;
use common\components\tasks\other\Partner5PackageFor50;
use common\components\tasks\other\PartnerInvestorDone;
use common\components\tasks\other\PartnerNovichokDone;
use common\components\tasks\other\PartnerPayCredit;
use common\components\tasks\other\PartnerUpgradeTask;
use common\components\tasks\other\SubscriptionFacebook;
use common\components\tasks\other\SubscriptionTelegram;
use common\components\tasks\other\SubscriptionTelegramGroup;
use common\components\tasks\other\SubscriptionTelegramPersonalBot;
use common\components\tasks\other\SubscriptionTwitter;
use common\components\tasks\other\SubscriptionYoutube;
use common\components\tasks\other\UpgradeCredit;
use common\components\tasks\other\WebWiseSnapTask;
use Yii;
use yii\base\Component;
use yii\helpers\ArrayHelper;

class TaskComponent extends Component
{

    const SUBSCRIPTION_FACEBOOK = 'subscription_facebook';
    const SUBSCRIPTION_TWITTER = 'subscription_twitter';
    const SUBSCRIPTION_TELEGRAM = 'subscription_telegram';
    const SUBSCRIPTION_TELEGRAM_GROUP = 'subscription_telegram_group';
    const SUBSCRIPTION_YOUTUBE = 'subscription_youtube';
    const ACTIVATED_PARTNER_PROGRAM = 'activated_partner_program';
    const PARTNER_NOVICHOK_DONE = 'child_user_done_task_beginner';
    const PARTNER_INVESTOR_DONE = 'child_user_done_task_investor';
    const PARTNER_TASK_PAY_CREDIT = 'partner_pay_credit';
    const PARTNER_UPGRADE_TASK = 'partner_upgrade_task';
    const INVESTOR_WEB_WISE_SNAP_TASK = 'web_wise_snap_task';
    const SUBSCRIPTION_TELEGRAM_PERSONAL_BOT = 'subscription_telegram_personal_bot';
    const PARTNER_5_PACKAGE_FOR_10 = 'partner_5_package_for_10';
    const PARTNER_5_PACKAGE_FOR_50 = 'partner_5_package_for_50';
    const INVOICE_BASKET_NOVICHOK_RWA_10 = 'invoice_basket_novichok_rwa_10';
    const INVOICE_BASKET_NOVICHOK_RWA_50 = 'invoice_basket_novichok_rwa_50';
    const INVOICE_BASKET_SPEC_PACKAGE_10 = 'invoice_basket_spec_package_10';
    const INVOICE_PREPAYMENTS = 'invoice_prepayments';
    const INVOICE_NEW_PACKAGE_NOT_CREDIT = 'invoice_new_package_not_credit';
    const INVOICE_CREDIT_FINISHED_FAST = 'invoice_credit_finished_fast';
    const UPGRADE_CREDIT = 'upgrade_credit';
    const OPEN_CONTRIBUTION = 'open_contribution';
    const INVOICE_CREDIT = 'invoice_credit';
    const INVOICE_CREDIT_DOUBLE = 'invoice_credit_double';
    const INVOICE_CREDIT_TRIPLE = 'invoice_credit_triple';
    const INVOICE_CREDIT_FINISHED = 'invoice_credit_finished';
    const BIG_GAME_REGISTRATION_LK = 'big_game_registration_lk';
    const BIG_GAME_DIGIU_INFO = 'big_game_digiu_info';
    const BIG_GAME_INVOICE_PACKAGE = 'big_game_invoice_package';
    const BIG_GAME_INVOICE_PACKAGE_FOR_NOVICHOK = 'big_game_invoice_package_for_novichok';
    const BIG_GAME_CREDIT_UPGRADE = 'big_game_credit_upgrade';
    const BIG_GAME_CREDIT_PAY = 'big_game_credit_pay';
    const BIG_GAME_PARTNER_NEW_10 = 'big_game_partner_new_10';
    const BIG_GAME_NEW_CHILD_1000 = 'big_game_new_child_1000';
    const BIG_GAME_INVOICE_CHILD_1000_MONTH = 'big_game_invoice_child_1000_month';
    const BIG_GAME_CONFIRM_PROFILE = 'big_game_confirm_profile';
    const BIG_GAME_LOOK_DIGIU = 'big_game_look_digiu';
    const BIG_GAME_WRITE_TG = 'big_game_write_tg';
    const BIG_GAME_INTERVIEW = 'big_game_interview';

    /**
     * @param int $type
     *
     * @return BaseInterface
     * @throws \Exception
     */
    public static function getInstance($type)
    {
        $classMap = [
            TaskComponent::SUBSCRIPTION_FACEBOOK => SubscriptionFacebook::class,
            TaskComponent::SUBSCRIPTION_TWITTER => SubscriptionTwitter::class,
            TaskComponent::SUBSCRIPTION_TELEGRAM => SubscriptionTelegram::class,
            TaskComponent::SUBSCRIPTION_TELEGRAM_GROUP => SubscriptionTelegramGroup::class,
            TaskComponent::SUBSCRIPTION_YOUTUBE => SubscriptionYoutube::class,
            TaskComponent::ACTIVATED_PARTNER_PROGRAM => ActivatedPartnerProgram::class,
            TaskComponent::PARTNER_NOVICHOK_DONE => PartnerNovichokDone::class,
            TaskComponent::PARTNER_INVESTOR_DONE => PartnerInvestorDone::class,
            TaskComponent::PARTNER_TASK_PAY_CREDIT => PartnerPayCredit::class,
            TaskComponent::PARTNER_UPGRADE_TASK => PartnerUpgradeTask::class,
            TaskComponent::INVESTOR_WEB_WISE_SNAP_TASK => WebWiseSnapTask::class,
            TaskComponent::SUBSCRIPTION_TELEGRAM_PERSONAL_BOT => SubscriptionTelegramPersonalBot::class,
            TaskComponent::PARTNER_5_PACKAGE_FOR_10 => Partner5PackageFor10::class,
            TaskComponent::PARTNER_5_PACKAGE_FOR_50 => Partner5PackageFor50::class,
            TaskComponent::INVOICE_BASKET_NOVICHOK_RWA_10 => InvoiceBasketNovichokRwa10::class,
            TaskComponent::INVOICE_BASKET_NOVICHOK_RWA_50 => InvoiceBasketNovichokRwa50::class,
            TaskComponent::INVOICE_BASKET_SPEC_PACKAGE_10 => InvoiceBasketSpecPackage10::class,
            TaskComponent::INVOICE_PREPAYMENTS => InvoicePrepayments::class,
            TaskComponent::INVOICE_NEW_PACKAGE_NOT_CREDIT => InvoiceNewPackageNotCredit::class,
            TaskComponent::INVOICE_CREDIT_FINISHED_FAST => InvoiceCreditFinishedFast::class,
            TaskComponent::UPGRADE_CREDIT => UpgradeCredit::class,
            TaskComponent::OPEN_CONTRIBUTION => OpenContribution::class,
            TaskComponent::INVOICE_CREDIT => InvoiceCredit::class,
            TaskComponent::INVOICE_CREDIT_DOUBLE => InvoiceCreditDouble::class,
            TaskComponent::INVOICE_CREDIT_TRIPLE => InvoiceCreditTriple::class,
            TaskComponent::INVOICE_CREDIT_FINISHED => InvoiceCreditFinished::class,

            TaskComponent::BIG_GAME_REGISTRATION_LK => BigGameRegistrationLK::class,
            TaskComponent::BIG_GAME_DIGIU_INFO => BigGameDigiuInfo::class,
            TaskComponent::BIG_GAME_INVOICE_PACKAGE => BigGameInvoicePackage::class,
            TaskComponent::BIG_GAME_INVOICE_PACKAGE_FOR_NOVICHOK => BigGameInvoicePackageForNovichok::class,
            TaskComponent::BIG_GAME_CREDIT_UPGRADE => BigGameCreditUpgrade::class,
            TaskComponent::BIG_GAME_CREDIT_PAY => BigGameCreditPay::class,
            TaskComponent::BIG_GAME_PARTNER_NEW_10 => BigGamePartnerNew10::class,
            TaskComponent::BIG_GAME_NEW_CHILD_1000 => BigGameNewChild1000::class,
            TaskComponent::BIG_GAME_INVOICE_CHILD_1000_MONTH => BigGameInvoiceChild1000Month::class,
            TaskComponent::BIG_GAME_CONFIRM_PROFILE => BigGameConfirmProfile::class,
            TaskComponent::BIG_GAME_LOOK_DIGIU => BigGameVideoRolik::class,
            TaskComponent::BIG_GAME_WRITE_TG => BigGameWriteTg::class,
            TaskComponent::BIG_GAME_INTERVIEW => BigGameInterview::class,
        ];

        $className = ArrayHelper::getValue($classMap, $type);
        if (empty($className)) {
            return new DefaultComponent();
        }

        return new $className;
    }

    /**
     * @param $system_check_code
     * @return string
     */
    public static function getBalanceType($system_check_code): string
    {
        $arrInvest = [
            TaskComponent::BIG_GAME_REGISTRATION_LK,
            TaskComponent::BIG_GAME_DIGIU_INFO,
            TaskComponent::BIG_GAME_INVOICE_PACKAGE,
            TaskComponent::BIG_GAME_INVOICE_PACKAGE_FOR_NOVICHOK,
            TaskComponent::BIG_GAME_CREDIT_UPGRADE,
            TaskComponent::BIG_GAME_CREDIT_PAY,
          //  TaskComponent::BIG_GAME_PARTNER_NEW_10 => BigGamePartnerNew10::class,
          //  TaskComponent::BIG_GAME_NEW_CHILD_1000 => BigGameNewChild1000::class,
         //   TaskComponent::BIG_GAME_INVOICE_CHILD_1000_MONTH => BigGameInvoiceChild1000Month::class,
        ];
        if(in_array($system_check_code, $arrInvest)){
            return 'invest';
        }
        return 'partner';
    }
}
