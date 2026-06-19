import { lazy, Suspense, useEffect, useMemo } from 'react';

import { ThemeProvider } from 'antipad-ui/src/ThemeProvider';
import { SnackbarProvider } from 'notistack';
import { Helmet, HelmetProvider } from 'react-helmet-async';
import { BrowserRouter, Route, Routes } from 'react-router';
import { Slide, ToastContainer } from 'react-toastify';

import { useAppStore } from '@app/store';
import { useGetUserInfo } from '@entities/user';
import { useGetUserSummary } from '@entities/user';
import { NotificationCenter } from '@features/notification-center';
import { StoriesTrigger } from '@features/stories-trigger';
import { ErrorPage } from '@pages/error';
import { LoginPage } from '@pages/login';
import { MainPage } from '@pages/main';
import ChunkBoundary from '@shared/components/ChunkBoundary';
import { ErrorMessage, SuccessMessage } from '@shared/components/Notistack';
import {
  ROUTE_DEPOSITS,
  ROUTE_FEATS,
  ROUTE_HOME,
  ROUTE_INVOICES,
  ROUTE_LOGIN,
  ROUTE_PROFILE,
  ROUTE_SHARES,
  ROUTE_TASKS,
  ROUTE_PAYMENT,
  ROUTE_WALLET_CREATE,
  ROUTE_PASSWORD_RESET,
  ROUTE_CREDIT_PACKAGE_INCREASE,
  ROUTE_WEALTH_WITHDRAW,
  ROUTE_TOP_UP,
  ROUTE_DEPOSIT_UPGRADE,
  ROUTE_PAYMENT_TOP_UP,
  ROUTE_CREDIT_PAYMENT,
  ROUTE_WITHDRAW,
  ROUTE_TRANSFER,
  ROUTE_CONVERT,
  ROUTE_VALIDATORS,
  ROUTE_TRANSFER_TO_OTHER,
  ROUTE_PURCHASE,
  ROUTE_CREDIT_DETAIL,
  ROUTE_INFO_PAGE,
  ROUTE_BILLS_PAYMENT,
  ROUTE_SHARES_PACKAGE,
  ROUTE_ERROR,
  ROUTE_ADD_HERITOR,
  ROUTE_STOCKS_INFO,
  ROUTE_FLEXIBLE_DEPOSIT_DETAILS,
  ROUTE_PARTNERS_DASHBOARD,
  ROUTE_PARTNER_ONBOARDING,
  ROUTE_BOT_ACTIVATION,
  ROUTE_PERSONAL_OFFERS,
  ROUTE_ACHIEVEMENTS_PERSONAL,
  ROUTE_NEWS_PAGE,
  ROUTE_FULL_NEWS,
  ROUTE_PROFILE_NON_CONFIRM,
  ROUTE_AI_CARD,
  ROUTE_ALPHA_MIND,
  ROUTE_ALPHA_MIND_EDUCATIONAL,
  ROUTE_ALPHA_MIND_VIDEOS,
  ROUTE_FULL_PROMO,
  ROUTE_PROMO_GIFTS,
  ROUTE_DIVIDENDS,
  ROUTE_ALPHA_MIND_DEMO,
  ROUTE_EVENTS,
  ROUTE_OPENCLAW,
  ROUTE_OPENCLAW_READER
} from '@shared/constants/routes';
import { STORAGE_LANGUAGE, STORAGE_TOKEN } from '@shared/constants/storage';
import { useRtl } from '@shared/hooks/useRtl';
import { useSignalRNotifications } from '@shared/hooks/useSignalRNotifications';
import { changeLanguage } from '@shared/utils/i18n';

import { MaintenanceMessage } from '..';
import { useHealthCheck } from '../../hooks';
import { ProtectedRoute } from '../../providers';

import './theme.css';

const TasksPage = lazy(() => import('@pages/tasks'));
const FeatsPage = lazy(() => import('@pages/feats'));
const SharesPage = lazy(() => import('@pages/shares'));
const DepositsPage = lazy(() => import('@pages/deposits'));
const ProfilePage = lazy(() => import('@pages/profile'));
const InvoicesPage = lazy(() => import('@pages/invoices'));
const PaymentPage = lazy(() => import('@pages/payment'));
const NotFoundPage = lazy(() => import('@pages/404'));
const PurchasePage = lazy(() => import('@pages/purchase'));
const WalletCreatePage = lazy(() => import('@pages/wallet-create'));
const PasswordResetPage = lazy(() => import('@pages/password-reset'));
const CreditPackageIncreasePage = lazy(() => import('@pages/credit-package-increase'));
const CreditDetailPage = lazy(() => import('@pages/credit-detail'));

const WealthWithdrawPage = lazy(() => import('@pages/wealth-withdraw'));
const TopUpPage = lazy(() => import('@pages/top-up'));
const UpgradePage = lazy(() => import('@pages/deposit-upgrade'));
const PaymentTopUpPage = lazy(() => import('@pages/payment-top-up'));
const AiChat = lazy(() => import('@widgets/ai-chat'));
const HappyDeskWidget = lazy(() => import('@widgets/happyDesk'));
const WithdrawPage = lazy(() => import('@pages/withdraw'));
const TransferPage = lazy(() => import('@pages/transfer'));
const ConvertPage = lazy(() => import('@pages/convert'));
const ValidatorsPage = lazy(() => import('@pages/validators'));
const TransferToOtherPage = lazy(() => import('@pages/transfer-to-other'));
const CreditPaymentPage = lazy(() => import('@pages/credit-payment'));
const ConfirmPersonalDataPage = lazy(() => import('@pages/confirm-personal-data'));
const ConfirmEmailChangePage = lazy(() => import('@pages/confirm-email-change'));
const InfoPage = lazy(() => import('@pages/info'));
const BillsPaymentPage = lazy(() => import('@pages/bills-payment'));
const AddHeritorPage = lazy(() => import('@pages/add-heritor'));
const StocksInfoPage = lazy(() => import('@pages/stocks-info'));
const PartnersDashboardPage = lazy(() => import('@pages/partners-dashboard'));
const RefererRegistrationPage = lazy(() => import('@pages/referer-registration'));
const SharesPackagePage = lazy(() => import('@pages/shares-package'));
const FlexibleDepositDetailsPage = lazy(() => import('@pages/flexible-deposit-details'));
const PartnerOnboardingPage = lazy(() => import('@pages/partner-onboarding'));
const BotActivationPage = lazy(() => import('@pages/bot-activation'));
const AchievementsPersonalPage = lazy(() => import('@pages/achievements-personal'));
const AchievementsSharePage = lazy(() => import('@pages/achievements-share'));
const PersonalOffersPage = lazy(() => import('@pages/personal-offers'));
const NewsPage = lazy(() => import('@pages/news-page'));
const PromoGiftsPage = lazy(() => import('@pages/promo-gifts'));
const FullNews = lazy(() => import('@pages/news-page/components/FullNews'));
const ProfileNonConfirm = lazy(() => import('@pages/profile-non-confirm'));
const ProfileConfirmationNotice = lazy(() => import('@pages/profile-confirmation'));
const AiCardPage = lazy(() => import('@pages/ai-card'));
const AlphaMindPage = lazy(() => import('@pages/alpha-mind'));
const AlphaMindEducationalPage = lazy(() => import('@pages/alpha-mind-educational'));
const AlphaMindVideosPage = lazy(() => import('@pages/alpha-mind-videos'));
const FullPromo = lazy(() => import('@pages/stocks-info/components/FullPromo'));
const Dividends = lazy(() => import('@pages/dividends'));
const AlphaMindDemo = lazy(() => import('@pages/alpha-mind-demo'));
const PhoneConfirmation = lazy(() => import('@pages/phone-confiramtion'));
const EventsPage = lazy(() => import('@pages/events'));
const OpenClawPage = lazy(() => import('@pages/open-claw'));

const OpenClawReaderPage = lazy(() => import('@pages/open-claw-reader'));

export const Preloader = () => {
  const { isAuthenticated, setIsAuthenticated, isConfirmationModalOpen } = useAppStore();

  const { isRtl } = useRtl();
  const isAppAvailable = useHealthCheck();

  const { refetch: getUserData } = useGetUserInfo({ enabled: false });

  const { refetch: getSummaryData } = useGetUserSummary({ enabled: false });

  useSignalRNotifications({ enabled: isAuthenticated });

  useEffect(() => {
    const token = sessionStorage.getItem(STORAGE_TOKEN) || localStorage.getItem(STORAGE_TOKEN);

    if (token) {
      setIsAuthenticated(true);
    }
  }, [setIsAuthenticated]);

  useEffect(() => {
    if (isAuthenticated) {
      getUserData();
      getSummaryData();
    }
  }, [getSummaryData, getUserData, isAuthenticated]);

  useEffect(() => {
    const userLanguage = localStorage.getItem(STORAGE_LANGUAGE) || 'en-US';

    changeLanguage(userLanguage);
  }, []);

  if (!isAppAvailable) {
    return (
      <ThemeProvider theme={{ palette: { mode: 'light' } }}>
        <MaintenanceMessage />
      </ThemeProvider>
    );
  }

  return (
    <ThemeProvider theme={{ palette: { mode: 'light' } }}>
      <SnackbarProvider
        autoHideDuration={1500}
        anchorOrigin={{ horizontal: isRtl ? 'left' : 'right', vertical: 'top' }}
        style={{ zIndex: 999999999 }}
        Components={{ error: ErrorMessage, success: SuccessMessage }}
      >
        <HelmetProvider>
          <Helmet defaultTitle="DigiU" titleTemplate="DigiU - %s" />
          <BrowserRouter>
            <ChunkBoundary>
              <Suspense>
                <Routes>
                  <Route path={ROUTE_LOGIN} element={<LoginPage />} />
                  <Route path={`${ROUTE_LOGIN}/:code`} element={<LoginPage />} />
                  <Route path={ROUTE_PASSWORD_RESET} element={<PasswordResetPage />} />
                  <Route path="auth">
                    <Route path="confirm-email-change" element={<ConfirmEmailChangePage />} />
                    <Route path=":tab" element={<ConfirmPersonalDataPage />} />
                    <Route path="registration">
                      <Route index element={<RefererRegistrationPage />} />
                      <Route path=":refCode" element={<RefererRegistrationPage />} />
                    </Route>
                  </Route>
                  <Route path="cabinet">
                    <Route path="profile">
                      <Route path="confirm-phone-new" element={<PhoneConfirmation />} />
                    </Route>
                  </Route>
                  <Route path={ROUTE_INFO_PAGE} element={<InfoPage />} />
                  <Route path={ROUTE_NEWS_PAGE} element={<NewsPage />} />
                  <Route path={ROUTE_PROMO_GIFTS} element={<PromoGiftsPage />} />
                  <Route
                    path={ROUTE_ALPHA_MIND_EDUCATIONAL}
                    element={<AlphaMindEducationalPage />}
                  />
                  <Route path={`${ROUTE_FULL_NEWS}/:year/:lang/:id`} element={<FullNews />} />
                  <Route path={ROUTE_ERROR} element={<ErrorPage />} />
                  {/* <Route path={`${ROUTE_YEAR_RESULTS}/:hash`} element={<YearResultsPage />} /> */}
                  <Route
                    path={`${ROUTE_ACHIEVEMENTS_PERSONAL}/:hash`}
                    element={<AchievementsSharePage />}
                  />
                  <Route element={<ProtectedRoute isAuthenticated={isAuthenticated} />}>
                    <Route path={ROUTE_PROFILE_NON_CONFIRM} element={<ProfileNonConfirm />} />
                    <Route path={ROUTE_HOME} element={<MainPage />} />
                    <Route path={ROUTE_TASKS} element={<TasksPage />} />
                    <Route path={ROUTE_FEATS} element={<FeatsPage />} />
                    <Route path={ROUTE_ALPHA_MIND} element={<AlphaMindPage />} />
                    <Route path={ROUTE_ALPHA_MIND_VIDEOS} element={<AlphaMindVideosPage />} />
                    <Route path={ROUTE_SHARES} element={<SharesPage />} />
                    <Route path={ROUTE_DIVIDENDS} element={<Dividends />} />
                    <Route path={`${ROUTE_DEPOSITS}`}>
                      <Route index element={<DepositsPage />} />
                      <Route path=":tab" element={<DepositsPage />} />
                    </Route>
                    <Route path={ROUTE_AI_CARD} element={<AiCardPage />} />
                    <Route path={`${ROUTE_INVOICES}`}>
                      <Route index element={<InvoicesPage />} />
                      <Route path=":tab" element={<InvoicesPage />} />
                    </Route>
                    <Route path={`${ROUTE_PARTNERS_DASHBOARD}`}>
                      <Route index element={<PartnersDashboardPage />} />
                      <Route path=":tab" element={<PartnersDashboardPage />} />
                    </Route>

                    {/* <Route path={ROUTE_PROFILE} element={<ProfilePage />} /> */}
                    <Route path={`${ROUTE_PROFILE}`}>
                      <Route index element={<ProfilePage />} />
                      <Route path=":tab" element={<ProfilePage />} />
                    </Route>
                    <Route path={ROUTE_INVOICES} element={<InvoicesPage />} />
                    <Route path={ROUTE_PURCHASE} element={<PurchasePage />} />
                    <Route path={ROUTE_ALPHA_MIND_DEMO} element={<AlphaMindDemo />} />

                    <Route path={`${ROUTE_OPENCLAW}`}>
                      <Route index element={<OpenClawPage />} />
                      <Route path=":tab" element={<OpenClawPage />} />
                    </Route>

                    <Route path={ROUTE_OPENCLAW_READER} element={<OpenClawReaderPage />} />

                    <Route path={ROUTE_WALLET_CREATE} element={<WalletCreatePage />} />
                    <Route
                      path={ROUTE_CREDIT_PACKAGE_INCREASE}
                      element={<CreditPackageIncreasePage />}
                    />
                    <Route path={ROUTE_CREDIT_DETAIL} element={<CreditDetailPage />} />
                    <Route path={ROUTE_PAYMENT} element={<PaymentPage />} />
                    <Route path={ROUTE_WEALTH_WITHDRAW} element={<WealthWithdrawPage />} />
                    <Route path={ROUTE_TOP_UP} element={<TopUpPage />} />
                    <Route path={ROUTE_DEPOSIT_UPGRADE} element={<UpgradePage />} />
                    <Route path={ROUTE_PAYMENT_TOP_UP} element={<PaymentTopUpPage />} />
                    <Route path={ROUTE_WITHDRAW} element={<WithdrawPage />} />
                    <Route path={ROUTE_TRANSFER} element={<TransferPage />} />
                    <Route path={ROUTE_CONVERT} element={<ConvertPage />} />
                    <Route path={ROUTE_PARTNER_ONBOARDING} element={<PartnerOnboardingPage />} />
                    <Route path={ROUTE_VALIDATORS} element={<ValidatorsPage />} />
                    <Route path={ROUTE_TRANSFER_TO_OTHER} element={<TransferToOtherPage />} />
                    <Route path={ROUTE_CREDIT_PAYMENT} element={<CreditPaymentPage />} />
                    <Route path={ROUTE_BILLS_PAYMENT} element={<BillsPaymentPage />} />
                    <Route path={ROUTE_ADD_HERITOR} element={<AddHeritorPage />} />
                    <Route path={`${ROUTE_STOCKS_INFO}`}>
                      <Route index element={<StocksInfoPage />} />
                      <Route path=":tab" element={<StocksInfoPage />} />
                    </Route>
                    <Route path={ROUTE_PARTNERS_DASHBOARD} element={<PartnersDashboardPage />} />
                    <Route path={ROUTE_SHARES_PACKAGE} element={<SharesPackagePage />} />
                    <Route path={ROUTE_PERSONAL_OFFERS} element={<PersonalOffersPage />} />
                    <Route path={`${ROUTE_FLEXIBLE_DEPOSIT_DETAILS}`}>
                      <Route index element={<FlexibleDepositDetailsPage />} />
                      <Route path=":tab" element={<FlexibleDepositDetailsPage />} />
                    </Route>
                    <Route path={ROUTE_BOT_ACTIVATION} element={<BotActivationPage />} />
                    <Route
                      path={ROUTE_ACHIEVEMENTS_PERSONAL}
                      element={<AchievementsPersonalPage />}
                    >
                      <Route index element={<AchievementsPersonalPage />} />
                      <Route path=":tab" element={<AchievementsPersonalPage />} />
                    </Route>
                    <Route path={ROUTE_EVENTS}>
                      <Route index element={<EventsPage />} />
                      <Route path=":id" element={<EventsPage />} />
                    </Route>
                    <Route path="*" element={<NotFoundPage />} />
                    <Route path={`${ROUTE_FULL_PROMO}/:id`} element={<FullPromo />} />
                  </Route>
                </Routes>
              </Suspense>
              <NotificationCenter />
            </ChunkBoundary>
          </BrowserRouter>
          <AiChat />
          <HappyDeskWidget />
        </HelmetProvider>

        <ToastContainer
          position="bottom-right"
          limit={3}
          style={{ zIndex: 999999999 }}
          transition={Slide}
        />
        <StoriesTrigger />
      </SnackbarProvider>
      {isConfirmationModalOpen && <ProfileConfirmationNotice />}
    </ThemeProvider>
  );
};
