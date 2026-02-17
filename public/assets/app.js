const state = {
  user: null,
  stats: { total_orders: 0, total_spent: 0 },
  services: [],
  serviceDirectory: {
    categories: [],
    categoryEntries: [],
    byProviderCategoryId: new Map(),
  },
  serviceIndex: {
    categories: [],
    categoryEntries: [],
    byCategory: new Map(),
    byCategorySortedByPrice: new Map(),
    byServiceId: new Map(),
    byProviderCategoryId: new Map(),
    categoryProviderId: new Map(),
    globalSortedByPrice: [],
    byExactName: new Map(),
    byOptionLabel: new Map(),
  },
  selectedServiceId: 0,
  servicesLoaded: false,
  servicesLoadingPromise: null,
  servicesSearchCache: new Map(),
  servicesSearchRequestId: 0,
  serviceSearchLast: {
    category: '',
    query: '',
    isIdQuery: false,
    results: [],
  },
  topServices: [],
  topServicesLoaded: false,
  orders: [],
  ordersLoaded: false,
  ordersRequestId: 0,
  refills: [],
  refillsLoaded: false,
  payment: null,
  paymentMethods: [],
  deposits: [],
  depositsLoaded: false,
  adminDeposits: [],
  adminDepositsLoaded: false,
  lastCheckout: null,
  adminPaymentOrders: [],
  adminPaymentOrdersLoaded: false,
  adminPaymentRequestId: 0,
  adminOrderHistory: [],
  adminOrderHistoryLoaded: false,
  adminOrderHistoryRequestId: 0,
  adminOrderHistoryFilter: {
    status: 'all',
    query: '',
    page: 1,
    perPage: 25,
    total: 0,
    totalPages: 1,
  },
  news: [],
  newsLoaded: false,
  newsMeta: {
    web_fetch_status: '',
    web_fetch_message: '',
  },
  adminNews: [],
  adminNewsLoaded: false,
  tickets: [],
  ticketsLoaded: false,
  ticketRequestId: 0,
  ticketDetail: null,
  ticketMessages: [],
  ticketFilter: {
    status: 'all',
    query: '',
  },
  csChat: {
    ticketId: 0,
    mode: 'bot',
    status: 'open',
    messages: [],
    lastMessageId: 0,
    loaded: false,
    unreadCount: 0,
  },
  adminCsChats: [],
  adminCsChatsLoaded: false,
  history: {
    status: 'ALL',
    idQuery: '',
    targetQuery: '',
    serviceQuery: '',
    perPage: 10,
    page: 1,
  },
  serviceCatalog: {
    query: '',
    category: '',
    sortBy: 'category_name',
    sortDir: 'asc',
    perPage: 50,
    page: 1,
  },
  serviceCatalogRows: [],
  serviceCatalogTotal: 0,
  serviceCatalogTotalPages: 1,
  serviceCatalogLoaded: false,
  serviceCatalogRequestId: 0,
  serviceOptionsRenderKey: '',
  lastResolvedCategory: '',
  panelHighlights: [],
  panelHighlightsLoaded: false,
  panelInfoMeta: {
    total_services: 0,
    total_categories: 0,
    synced_at: '',
  },
  gameServices: [],
  gameServicesLoaded: false,
  gameServicesRequestId: 0,
  gameServiceSearchCache: new Map(),
  gameSelectedService: null,
  gameOrders: [],
  gameOrdersLoaded: false,
  gameOrdersRequestId: 0,
  gameAdminPendingOrders: [],
  gameAdminPendingLoaded: false,
  gameAdminPendingRequestId: 0,
  gameHistoryFilter: {
    status: 'ALL',
    idQuery: '',
    serviceQuery: '',
  },
  panelInfoClosed: false,
  hasTop5Data: false,
  currentView: 'utama',
};

const NEWS_SOURCE_BRAND = 'Odyssiavault';
const PANEL_INFO_STORAGE_KEY = 'odyssiavault_panel_info_closed';
const MAX_CATEGORY_OPTIONS = 140;
const MAX_SERVICE_OPTIONS = 180;
const DEFAULT_SERVICE_VARIANT = 'services_1';
const HIGHLIGHT_SERVICE_VARIANT = 'services_1';
const SERVICES_SEARCH_CACHE_MAX_KEYS = 80;
const API_REQUEST_TIMEOUT_MS = 35000;
const API_GET_CACHE_TTL_MS = 30000;
const API_GET_CACHE_MAX_ITEMS = 120;
const ADMIN_PENDING_POLL_MS = 30000;
const PENDING_PAYMENT_SYNC_POLL_MS = 22000;
const PENDING_PAYMENT_SYNC_MIN_GAP_MS = 8000;
const PENDING_PAYMENT_SYNC_MAX_ORDERS = 3;
const SESSION_FETCH_RETRY_ATTEMPTS = 3;
const SESSION_FETCH_RETRY_DELAY_MS = 480;
const HOSTING_CHALLENGE_RELOAD_KEY = 'odyssiavault_hosting_challenge_reload_at';
const HOSTING_CHALLENGE_RELOAD_COOLDOWN_MS = 20000;
const IOS_LIKE_USER_AGENT = typeof navigator !== 'undefined'
  ? /iPad|iPhone|iPod/.test(navigator.userAgent || '')
    || (navigator.platform === 'MacIntel' && Number(navigator.maxTouchPoints || 0) > 1)
  : false;
const IS_IOS_DEVICE = IOS_LIKE_USER_AGENT;
const CATEGORY_OPTIONS_LIMIT = IS_IOS_DEVICE ? 90 : MAX_CATEGORY_OPTIONS;
const SERVICE_OPTIONS_LIMIT = IS_IOS_DEVICE ? 100 : MAX_SERVICE_OPTIONS;
const SERVICE_QUERY_MIN_CHARS = 1;
const GAME_QUERY_MIN_CHARS = 1;
const GAME_SEARCH_DEBOUNCE_MS = IS_IOS_DEVICE ? 190 : 130;
const GAME_SEARCH_CACHE_MAX_KEYS = 120;
const CATEGORY_INPUT_DEBOUNCE_MS = IS_IOS_DEVICE ? 130 : 90;
const SERVICE_INPUT_DEBOUNCE_MS = IS_IOS_DEVICE ? 170 : 110;
const CATALOG_INPUT_DEBOUNCE_MS = IS_IOS_DEVICE ? 340 : 240;
const ADMIN_HISTORY_INPUT_DEBOUNCE_MS = IS_IOS_DEVICE ? 360 : 240;
const CS_CHAT_POLL_MS = IS_IOS_DEVICE ? 9000 : 7000;
const CS_CHAT_ADMIN_POLL_MS = IS_IOS_DEVICE ? 16000 : 12000;
const ID_NUMBER_FORMATTER = new Intl.NumberFormat('id-ID');
const CURRENCY_FORMATTER = new Intl.NumberFormat('id-ID', {
  style: 'currency',
  currency: 'IDR',
  maximumFractionDigits: 0,
});
const CURRENCY_UNIT_FORMATTER = new Intl.NumberFormat('id-ID', {
  style: 'currency',
  currency: 'IDR',
  minimumFractionDigits: 0,
  maximumFractionDigits: 3,
});
const ID_COLLATOR = new Intl.Collator('id', { sensitivity: 'base', numeric: true });
const apiGetCache = new Map();
const apiInFlight = new Map();
let adminPendingPollTimer = null;
let pendingPaymentSyncTimer = null;
let pendingPaymentSyncInFlight = false;
let pendingPaymentSyncLastRunAt = 0;
let adminPendingInitialized = false;
let adminNotificationPermissionAsked = false;
let adminSeenPendingOrderIds = new Set();
let adminToastContainerEl = null;
let serviceInfoRafId = 0;
let categorySuggestionItems = [];
let serviceSuggestionItems = [];
let gameServiceSuggestionItems = [];
let categorySuggestRenderKey = '';
let serviceSuggestRenderKey = '';
let gameSuggestRenderKey = '';
let csChatPollTimer = null;
let csChatAdminPollTimer = null;
let csChatRequestInFlight = false;
let csChatAdminRequestInFlight = false;

const pageEl = document.querySelector('.page');
const PAYMENT_GATEWAY_ENABLED = String(pageEl?.dataset?.paymentGatewayEnabled || '').trim() === '1';
const PAYMENT_GATEWAY_PROVIDER = String(pageEl?.dataset?.paymentGatewayProvider || 'custom').trim().toLowerCase();
const GAME_TOPUP_COMING_SOON = String(pageEl?.dataset?.gameTopupComingSoon || '').trim() === '1';
if (typeof document !== 'undefined') {
  document.documentElement.classList.toggle('ios-device', IS_IOS_DEVICE);
  if (document.body) {
    document.body.classList.toggle('ios-device', IS_IOS_DEVICE);
  }
}
const PANEL_VIEWS = new Set(['utama', 'dashboard', 'profile', 'top5', 'purchase', 'refill', 'game', 'deposit', 'ticket', 'services', 'pages', 'admin']);
const LEGACY_VIEW_MAP = {
  home: 'utama',
  beranda: 'utama',
  main: 'utama',
  mainpage: 'utama',
  landing: 'utama',
  utama: 'utama',
  dashboardsection: 'dashboard',
  profilesection: 'profile',
  top5section: 'top5',
  ordersection: 'purchase',
  historysection: 'purchase',
  refillsection: 'refill',
  gamesection: 'game',
  depositsection: 'deposit',
  ticketsection: 'ticket',
  servicessection: 'services',
  contactsection: 'pages',
  adminsection: 'admin',
};

const authView = document.getElementById('authView');
const appView = document.getElementById('appView');
const panelNavLinks = Array.from(document.querySelectorAll('.menu a[data-view]'));
const adminMenuLinkEl = document.getElementById('adminMenuLink');
const viewSections = Array.from(document.querySelectorAll('[data-view-section]'));
const sideTipsEls = Array.from(document.querySelectorAll('.tip'));
const topbarTitleEl = document.getElementById('topbarTitle');

const tabLogin = document.getElementById('tabLogin');
const tabRegister = document.getElementById('tabRegister');
const loginForm = document.getElementById('loginForm');
const loginPasswordEl = document.getElementById('loginPassword');
const loginPasswordToggleEl = document.getElementById('loginPasswordToggle');
const registerForm = document.getElementById('registerForm');
const authNotice = document.getElementById('authNotice');

const welcomeText = document.getElementById('welcomeText');
const statBalance = document.getElementById('statBalance');
const statOrders = document.getElementById('statOrders');
const statSpent = document.getElementById('statSpent');
const panelInfoSectionEl = document.getElementById('panelInfoSection');
const panelInfoTickerTextEl = document.getElementById('panelInfoTickerText');
const panelInfoRefreshBtnEl = document.getElementById('panelInfoRefreshBtn');
const panelInfoCloseBtnEl = document.getElementById('panelInfoCloseBtn');
const dashboardHighlightsEl = document.getElementById('dashboardHighlights');
const servicesSyncMetaEl = document.getElementById('servicesSyncMeta');
const quickViewButtons = Array.from(document.querySelectorAll('[data-quick-view]'));
const profileUsernameEl = document.getElementById('profileUsername');
const profileRoleEl = document.getElementById('profileRole');
const profileEmailEl = document.getElementById('profileEmail');
const profileCreatedAtEl = document.getElementById('profileCreatedAt');
const profileLastLoginAtEl = document.getElementById('profileLastLoginAt');
const profileTotalSpentEl = document.getElementById('profileTotalSpent');
const profileTotalOrdersEl = document.getElementById('profileTotalOrders');
const profileWaitingOrdersEl = document.getElementById('profileWaitingOrders');
const profileProcessingOrdersEl = document.getElementById('profileProcessingOrders');
const profileCompletedOrdersEl = document.getElementById('profileCompletedOrders');

const categoryInputEl = document.getElementById('categoryInput');
const categoryOptionsEl = document.getElementById('categoryOptions');
const categorySuggestPanelEl = document.getElementById('categorySuggestPanel');
const serviceInputEl = document.getElementById('serviceInput');
const serviceOptionsEl = document.getElementById('serviceOptions');
const serviceSuggestPanelEl = document.getElementById('serviceSuggestPanel');
const categorySuggestFieldEl = categoryInputEl ? categoryInputEl.closest('.suggestion-field') : null;
const serviceSuggestFieldEl = serviceInputEl ? serviceInputEl.closest('.suggestion-field') : null;
const targetEl = document.getElementById('target');
const quantityEl = document.getElementById('quantity');
const quantityGroupEl = quantityEl ? quantityEl.closest('div') : null;
const quantityLabelEl = quantityGroupEl ? quantityGroupEl.querySelector('label') : null;
const commentGroupEl = document.getElementById('commentGroup');
const commentHintEl = document.getElementById('commentHint');
const mentionGroupEl = document.getElementById('mentionGroup');
const mentionHintEl = document.getElementById('mentionHint');
const advancedFieldsEl = document.getElementById('advancedFields');
const komenEl = document.getElementById('komen');
const commentsEl = document.getElementById('comments');
const usernamesEl = document.getElementById('usernames');
const singleUsernameEl = document.getElementById('singleUsername');
const hashtagsEl = document.getElementById('hashtags');
const keywordsEl = document.getElementById('keywords');
const serviceInfoEl = document.getElementById('serviceInfo');
const orderNotice = document.getElementById('orderNotice');
const orderFormEl = document.getElementById('orderForm');
const pricePer1000El = document.getElementById('pricePer1000');
const top5SectionEl = document.getElementById('top5Section');
const top5ListEl = document.getElementById('top5List');
const top5EmptyStateEl = document.getElementById('top5EmptyState');
const emergencyServiceTextEl = document.getElementById('emergencyServiceText');
const gameOrderFormEl = document.getElementById('gameOrderForm');
const gameCategorySelectEl = document.getElementById('gameCategorySelect');
const gameServiceInputEl = document.getElementById('gameServiceInput');
const gameServiceSuggestPanelEl = document.getElementById('gameServiceSuggestPanel');
const gameSuggestFieldEl = gameServiceInputEl ? gameServiceInputEl.closest('.suggestion-field') : null;
const gameTargetInputEl = document.getElementById('gameTargetInput');
const gameContactInputEl = document.getElementById('gameContactInput');
const gamePriceViewEl = document.getElementById('gamePriceView');
const gameServiceInfoEl = document.getElementById('gameServiceInfo');
const gameOrderNoticeEl = document.getElementById('gameOrderNotice');
const gameOrdersBodyEl = document.getElementById('gameOrdersBody');
const gameOrderNoticeBottomEl = document.getElementById('gameOrderNoticeBottom');
const gameHistorySummaryEl = document.getElementById('gameHistorySummary');
const gameHistoryStatusEl = document.getElementById('gameHistoryStatus');
const gameHistoryIdSearchEl = document.getElementById('gameHistoryIdSearch');
const gameHistoryServiceSearchEl = document.getElementById('gameHistoryServiceSearch');
const gameHistoryRefreshBtnEl = document.getElementById('gameHistoryRefreshBtn');
const gameAdminPendingPanelEl = document.getElementById('gameAdminPendingPanel');
const gameAdminPendingBodyEl = document.getElementById('gameAdminPendingBody');
const gameAdminPendingNoticeEl = document.getElementById('gameAdminPendingNotice');

const newsListEl = document.getElementById('newsList');
const newsAdminSectionEl = document.getElementById('newsAdminSection');
const newsFormEl = document.getElementById('newsForm');
const newsIdEl = document.getElementById('newsId');
const newsTitleEl = document.getElementById('newsTitle');
const newsPublishedAtEl = document.getElementById('newsPublishedAt');
const newsSourceNameEl = document.getElementById('newsSourceName');
const newsSourceUrlEl = document.getElementById('newsSourceUrl');
const newsSummaryEl = document.getElementById('newsSummary');
const newsContentEl = document.getElementById('newsContent');
const newsIsPublishedEl = document.getElementById('newsIsPublished');
const newsNoticeEl = document.getElementById('newsNotice');
const newsAdminBodyEl = document.getElementById('newsAdminBody');
const newsResetBtnEl = document.getElementById('newsResetBtn');

const newsModalEl = document.getElementById('newsModal');
const newsModalCloseEl = document.getElementById('newsModalClose');
const newsModalTitleEl = document.getElementById('newsModalTitle');
const newsModalMetaEl = document.getElementById('newsModalMeta');
const newsModalContentEl = document.getElementById('newsModalContent');
const newsModalSourceEl = document.getElementById('newsModalSource');
const newsModalSourceNameEl = document.getElementById('newsModalSourceName');
const paymentQrModalEl = document.getElementById('paymentQrModal');
const paymentQrModalCloseEl = document.getElementById('paymentQrModalClose');
const paymentQrTitleEl = document.getElementById('paymentQrTitle');
const paymentQrSummaryEl = document.getElementById('paymentQrSummary');
const paymentQrImageEl = document.getElementById('paymentQrImage');
const paymentQrInstructionEl = document.getElementById('paymentQrInstruction');
const paymentQrNoticeEl = document.getElementById('paymentQrNotice');
const paymentQrConfirmBtnEl = document.getElementById('paymentQrConfirmBtn');
const paymentQrToHistoryEl = document.getElementById('paymentQrToHistory');
const historySectionEl = document.getElementById('historySection');
const gameSectionEl = document.getElementById('gameSection');

const ordersBody = document.getElementById('ordersBody');
const ordersNotice = document.getElementById('ordersNotice');
const ordersPaginationEl = document.getElementById('ordersPagination');
const historyStatusTabsEl = document.getElementById('historyStatusTabs');
const historyOrderIdSearchEl = document.getElementById('historyOrderIdSearch');
const historyTargetSearchEl = document.getElementById('historyTargetSearch');
const historyServiceSearchEl = document.getElementById('historyServiceSearch');
const historyPerPageEl = document.getElementById('historyPerPage');
const historySummaryEl = document.getElementById('historySummary');
const refillFormEl = document.getElementById('refillForm');
const refillOrderIdEl = document.getElementById('refillOrderId');
const refillSummaryEl = document.getElementById('refillSummary');
const refillBodyEl = document.getElementById('refillBody');
const refillNoticeEl = document.getElementById('refillNotice');
const refillStatusNoticeEl = document.getElementById('refillStatusNotice');

const checkoutPanelEl = document.getElementById('checkoutPanel');
const checkoutSummaryEl = document.getElementById('checkoutSummary');
const checkoutMethodsEl = document.getElementById('checkoutMethods');
const paymentConfirmFormEl = document.getElementById('paymentConfirmForm');
const paymentConfirmBtnEl = document.getElementById('paymentConfirmBtn');
const paymentMethodSelectEl = document.getElementById('paymentMethodSelect');
const paymentReferenceEl = document.getElementById('paymentReference');
const paymentPayerNameEl = document.getElementById('paymentPayerName');
const paymentPayerNoteEl = document.getElementById('paymentPayerNote');
const paymentConfirmNoticeEl = document.getElementById('paymentConfirmNotice');

const adminPaymentSectionEl = document.getElementById('adminPaymentSection');
const adminPaymentBodyEl = document.getElementById('adminPaymentBody');
const adminPaymentNoticeEl = document.getElementById('adminPaymentNotice');
const adminOrderHistorySectionEl = document.getElementById('adminOrderHistorySection');
const adminOrderHistorySearchEl = document.getElementById('adminOrderHistorySearch');
const adminOrderHistoryStatusEl = document.getElementById('adminOrderHistoryStatus');
const adminOrderHistoryPerPageEl = document.getElementById('adminOrderHistoryPerPage');
const adminOrderHistoryRefreshBtnEl = document.getElementById('adminOrderHistoryRefreshBtn');
const adminOrderHistorySummaryEl = document.getElementById('adminOrderHistorySummary');
const adminOrderHistoryBodyEl = document.getElementById('adminOrderHistoryBody');
const adminOrderHistoryPaginationEl = document.getElementById('adminOrderHistoryPagination');
const adminOrderHistoryNoticeEl = document.getElementById('adminOrderHistoryNotice');
const adminCsSectionEl = document.getElementById('adminCsSection');
const adminCsBodyEl = document.getElementById('adminCsBody');
const adminCsNoticeEl = document.getElementById('adminCsNotice');
const adminCsRefreshBtnEl = document.getElementById('adminCsRefreshBtn');

const depositFormEl = document.getElementById('depositForm');
const depositAmountEl = document.getElementById('depositAmount');
const depositPayerNameEl = document.getElementById('depositPayerName');
const depositPayerNoteEl = document.getElementById('depositPayerNote');
const depositNoticeEl = document.getElementById('depositNotice');
const qrisImageEl = document.getElementById('qrisImage');
const qrisMetaEl = document.getElementById('qrisMeta');
const depositInstructionEl = document.getElementById('depositInstruction');
const depositHistoryBodyEl = document.getElementById('depositHistoryBody');
const depositAdminPanelEl = document.getElementById('depositAdminPanel');
const depositAdminBodyEl = document.getElementById('depositAdminBody');
const depositAdminNoticeEl = document.getElementById('depositAdminNotice');

const serviceCatalogSearchEl = document.getElementById('serviceCatalogSearch');
const serviceCatalogCategoryEl = document.getElementById('serviceCatalogCategory');
const serviceCatalogSortByEl = document.getElementById('serviceCatalogSortBy');
const serviceCatalogSortDirEl = document.getElementById('serviceCatalogSortDir');
const servicesCatalogPerPageEl = document.getElementById('servicesCatalogPerPage');
const servicesCatalogBodyEl = document.getElementById('servicesCatalogBody');
const servicesCatalogSummaryEl = document.getElementById('servicesCatalogSummary');
const servicesCatalogPaginationEl = document.getElementById('servicesCatalogPagination');

const btnRefresh = document.getElementById('btnRefresh');
const btnLogout = document.getElementById('btnLogout');
const accountMenuEl = document.getElementById('accountMenu');
const accountMenuToggleEl = document.getElementById('accountMenuToggle');
const accountMenuPanelEl = document.getElementById('accountMenuPanel');
const accountAvatarEl = document.getElementById('accountAvatar');
const accountMenuNameEl = document.getElementById('accountMenuName');
const accountMenuRoleEl = document.getElementById('accountMenuRole');
const btnOpenProfileEl = document.getElementById('btnOpenProfile');
const btnOpenSettingsEl = document.getElementById('btnOpenSettings');
const profilePasswordFormEl = document.getElementById('profilePasswordForm');
const currentPasswordEl = document.getElementById('currentPassword');
const newPasswordEl = document.getElementById('newPassword');
const confirmPasswordEl = document.getElementById('confirmPassword');
const profilePasswordNoticeEl = document.getElementById('profilePasswordNotice');
const ticketFormEl = document.getElementById('ticketForm');
const ticketSubjectEl = document.getElementById('ticketSubject');
const ticketCategoryEl = document.getElementById('ticketCategory');
const ticketOrderIdEl = document.getElementById('ticketOrderId');
const ticketPriorityEl = document.getElementById('ticketPriority');
const ticketMessageEl = document.getElementById('ticketMessage');
const ticketNoticeEl = document.getElementById('ticketNotice');
const ticketRefreshBtnEl = document.getElementById('ticketRefreshBtn');
const ticketStatusFilterEl = document.getElementById('ticketStatusFilter');
const ticketSearchInputEl = document.getElementById('ticketSearchInput');
const ticketBodyEl = document.getElementById('ticketBody');
const ticketDetailPanelEl = document.getElementById('ticketDetailPanel');
const ticketDetailTitleEl = document.getElementById('ticketDetailTitle');
const ticketDetailMetaEl = document.getElementById('ticketDetailMeta');
const ticketMessagesEl = document.getElementById('ticketMessages');
const ticketReplyMessageEl = document.getElementById('ticketReplyMessage');
const ticketReplyBtnEl = document.getElementById('ticketReplyBtn');
const ticketCloseBtnEl = document.getElementById('ticketCloseBtn');
const ticketReopenBtnEl = document.getElementById('ticketReopenBtn');
const ticketCloseDetailBtnEl = document.getElementById('ticketCloseDetailBtn');
const ticketDetailNoticeEl = document.getElementById('ticketDetailNotice');
const shareNativeBtnEl = document.getElementById('shareNativeBtn');
const shareCopyBtnEl = document.getElementById('shareCopyBtn');
const shareWebsiteUrlEl = document.getElementById('shareWebsiteUrl');
const shareNoticeEl = document.getElementById('shareNotice');
const shareProviderButtons = Array.from(document.querySelectorAll('[data-share-provider]'));
const csChatWidgetEl = document.getElementById('csChatWidget');
const csChatToggleEl = document.getElementById('csChatToggle');
const csChatPanelEl = document.getElementById('csChatPanel');
const csChatUnreadEl = document.getElementById('csChatUnread');
const csChatModeTextEl = document.getElementById('csChatModeText');
const csChatTakeoverBtnEl = document.getElementById('csChatTakeoverBtn');
const csChatCloseBtnEl = document.getElementById('csChatCloseBtn');
const csChatMessagesEl = document.getElementById('csChatMessages');
const csChatNoticeEl = document.getElementById('csChatNotice');
const csChatFormEl = document.getElementById('csChatForm');
const csChatInputEl = document.getElementById('csChatInput');
const csChatSendBtnEl = document.getElementById('csChatSendBtn');

function rupiah(value) {
  return CURRENCY_FORMATTER.format(Number(value || 0));
}

function rupiahUnit(value) {
  return CURRENCY_UNIT_FORMATTER.format(Number(value || 0));
}

function formatInteger(value) {
  return ID_NUMBER_FORMATTER.format(Number(value || 0));
}

function escapeHtml(input) {
  return String(input ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function formatDateTime(value) {
  if (!value) return '-';
  const parsed = new Date(value.replace(' ', 'T'));
  if (Number.isNaN(parsed.getTime())) return String(value);

  return new Intl.DateTimeFormat('id-ID', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  }).format(parsed);
}

function toDatetimeLocalValue(value) {
  if (!value) return '';
  const parsed = new Date(value.replace(' ', 'T'));
  if (Number.isNaN(parsed.getTime())) return '';
  const pad = (num) => String(num).padStart(2, '0');

  return [
    parsed.getFullYear(),
    '-',
    pad(parsed.getMonth() + 1),
    '-',
    pad(parsed.getDate()),
    'T',
    pad(parsed.getHours()),
    ':',
    pad(parsed.getMinutes()),
  ].join('');
}

function resolveAssetPath(path) {
  const value = String(path || '').trim();
  if (!value) return './assets/qris.png';
  if (value.startsWith('http://') || value.startsWith('https://') || value.startsWith('//') || value.startsWith('/')) {
    return value;
  }

  return value.startsWith('./') ? value : `./${value.replace(/^\.?\//, '')}`;
}

function parsePaymentMethodsFromPage() {
  const raw = pageEl?.dataset?.paymentMethods || '[]';
  try {
    const parsed = JSON.parse(raw);
    if (!Array.isArray(parsed)) return [];

    return parsed
      .map((item) => ({
        code: String(item?.code || '').trim().toLowerCase(),
        name: String(item?.name || '').trim(),
        account_name: String(item?.account_name || '').trim(),
        account_number: String(item?.account_number || '').trim(),
        note: String(item?.note || '').trim(),
      }))
      .filter((item) => item.code && item.name && item.account_number);
  } catch {
    return [];
  }
}

function getShareLandingUrl() {
  const configured = String(pageEl?.dataset?.shareUrl || '').trim();
  if (configured) {
    return configured;
  }

  if (typeof window === 'undefined' || !window.location) {
    return '';
  }

  const origin = String(window.location.origin || '').trim();
  const pathRaw = String(window.location.pathname || '/');
  const basePath = pathRaw.endsWith('/')
    ? pathRaw
    : pathRaw.replace(/\/[^/]*$/, '/');

  if (origin) {
    return `${origin}${basePath}`;
  }

  return basePath || '/';
}

function getSharePayload() {
  const appName = String(pageEl?.dataset?.appName || 'Odyssiavault').trim() || 'Odyssiavault';
  const url = getShareLandingUrl();
  const text = `${appName} - Platform top up digital cepat dan terpercaya.`;
  const fullText = `${text}\n${url}`.trim();

  return {
    appName,
    title: `${appName} - Panel Topup`,
    text,
    fullText,
    url,
  };
}

async function copyTextToClipboard(value) {
  const text = String(value || '').trim();
  if (!text) return false;

  if (typeof navigator !== 'undefined' && navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
    try {
      await navigator.clipboard.writeText(text);
      return true;
    } catch {
      // Fallback to execCommand when clipboard API fails.
    }
  }

  try {
    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', 'readonly');
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    textarea.style.left = '-9999px';
    textarea.style.top = '0';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();
    const copied = document.execCommand('copy');
    document.body.removeChild(textarea);
    return !!copied;
  } catch {
    return false;
  }
}

function openExternalShare(url) {
  const target = String(url || '').trim();
  if (!target) return false;
  const popup = window.open(target, '_blank', 'noopener,noreferrer');
  if (popup && typeof popup.opener !== 'undefined') {
    popup.opener = null;
  }
  return !!popup;
}

function shareProviderLabel(provider) {
  const key = String(provider || '').toLowerCase();
  if (key === 'whatsapp') return 'WhatsApp';
  if (key === 'telegram') return 'Telegram';
  if (key === 'discord') return 'Discord';
  if (key === 'instagram') return 'Instagram';
  if (key === 'facebook') return 'Facebook';
  if (key === 'x') return 'X';
  if (key === 'linkedin') return 'LinkedIn';
  if (key === 'line') return 'Line';
  if (key === 'email') return 'Email';
  return 'Aplikasi';
}

function buildShareProviderUrl(provider, payload) {
  const key = String(provider || '').toLowerCase();
  const shareUrl = encodeURIComponent(payload.url || '');
  const shareText = encodeURIComponent(payload.text || '');
  const shareFullText = encodeURIComponent(payload.fullText || '');
  const shareTitle = encodeURIComponent(payload.title || 'Odyssiavault');

  switch (key) {
    case 'whatsapp':
      return `https://wa.me/?text=${shareFullText}`;
    case 'telegram':
      return `https://t.me/share/url?url=${shareUrl}&text=${shareText}`;
    case 'facebook':
      return `https://www.facebook.com/sharer/sharer.php?u=${shareUrl}`;
    case 'x':
      return `https://twitter.com/intent/tweet?url=${shareUrl}&text=${shareText}`;
    case 'linkedin':
      return `https://www.linkedin.com/sharing/share-offsite/?url=${shareUrl}`;
    case 'line':
      return `https://social-plugins.line.me/lineit/share?url=${shareUrl}`;
    case 'email':
      return `mailto:?subject=${shareTitle}&body=${shareFullText}`;
    case 'discord':
      return 'https://discord.com/app';
    case 'instagram':
      return 'https://www.instagram.com/';
    default:
      return '';
  }
}

function updateShareSectionState() {
  const payload = getSharePayload();
  if (shareWebsiteUrlEl) {
    shareWebsiteUrlEl.value = payload.url || '';
  }

  if (shareNativeBtnEl) {
    const supportsNativeShare = typeof navigator !== 'undefined' && typeof navigator.share === 'function';
    shareNativeBtnEl.textContent = supportsNativeShare ? 'Bagikan Sekarang' : 'Bagikan (Copy Link)';
  }
}

async function handleShareProvider(provider) {
  const payload = getSharePayload();
  if (!payload.url) {
    showNotice(shareNoticeEl, 'err', 'Link website tidak tersedia untuk dibagikan.');
    return;
  }

  const providerKey = String(provider || '').toLowerCase();
  const label = shareProviderLabel(providerKey);
  const targetUrl = buildShareProviderUrl(providerKey, payload);
  const shouldCopyFirst = providerKey === 'discord' || providerKey === 'instagram';

  if (shouldCopyFirst) {
    const copied = await copyTextToClipboard(payload.fullText);
    if (copied) {
      showNotice(shareNoticeEl, 'ok', `Link disalin. Tempel di ${label}.`);
    } else {
      showNotice(shareNoticeEl, 'info', `Buka ${label}, lalu bagikan link secara manual.`);
    }
  }

  if (!targetUrl) {
    const copied = await copyTextToClipboard(payload.fullText);
    if (copied) {
      showNotice(shareNoticeEl, 'ok', 'Link website berhasil disalin.');
    } else {
      showNotice(shareNoticeEl, 'err', 'Gagal menyalin link website.');
    }
    return;
  }

  const opened = openExternalShare(targetUrl);
  if (!opened) {
    const copied = await copyTextToClipboard(payload.fullText);
    if (copied) {
      showNotice(shareNoticeEl, 'info', 'Popup diblokir browser. Link sudah disalin, tinggal tempel manual.');
    } else {
      showNotice(shareNoticeEl, 'err', `Gagal membuka ${label}. Izinkan popup lalu coba lagi.`);
    }
    return;
  }

  if (!shouldCopyFirst) {
    showNotice(shareNoticeEl, 'ok', `Membuka ${label}...`);
  }
}

async function handleNativeShare() {
  const payload = getSharePayload();
  if (!payload.url) {
    showNotice(shareNoticeEl, 'err', 'Link website tidak tersedia untuk dibagikan.');
    return;
  }

  const supportsNativeShare = typeof navigator !== 'undefined' && typeof navigator.share === 'function';
  if (supportsNativeShare) {
    try {
      await navigator.share({
        title: payload.title,
        text: payload.text,
        url: payload.url,
      });
      showNotice(shareNoticeEl, 'ok', 'Berhasil membuka menu share di perangkat kamu.');
      return;
    } catch (error) {
      if (error && error.name === 'AbortError') {
        return;
      }
    }
  }

  const copied = await copyTextToClipboard(payload.fullText);
  if (copied) {
    showNotice(shareNoticeEl, 'ok', 'Link website berhasil disalin. Tempel ke aplikasi yang kamu pilih.');
  } else {
    showNotice(shareNoticeEl, 'err', 'Gagal menyalin link website.');
  }
}

function normalizeQuery(value) {
  return String(value || '').trim().toLowerCase();
}

function debounce(fn, wait = 120) {
  let timer = null;
  return (...args) => {
    if (timer) {
      clearTimeout(timer);
    }
    timer = setTimeout(() => {
      timer = null;
      fn(...args);
    }, wait);
  };
}

function waitMs(ms) {
  return new Promise((resolve) => {
    setTimeout(resolve, Math.max(0, Number(ms || 0)));
  });
}

function normalizeApiErrorMessage(data) {
  return normalizeQuery(data?.data?.msg || '');
}

function isTransientSessionFailure(data) {
  const msg = normalizeApiErrorMessage(data);
  if (!msg) return true;

  if (
    msg.includes('belum login')
    || msg.includes('unauthorized')
    || msg.includes('akses ditolak')
  ) {
    return false;
  }

  return [
    'timeout',
    'terlalu lama',
    'tidak dapat terhubung',
    'respon server bukan json',
    'respon json server tidak valid',
    'kesalahan server',
    'gateway',
    'bad gateway',
    'service unavailable',
    'internal server error',
    'permintaan terlalu cepat',
    'ratelimit',
    'rate limit',
    'temporarily unavailable',
  ].some((keyword) => msg.includes(keyword));
}

function isRetryableApiFailure(data) {
  const msg = normalizeApiErrorMessage(data);
  if (!msg) return false;
  if (msg.includes('silakan login') || msg.includes('akses ditolak') || msg.includes('metode pembayaran tidak valid')) {
    return false;
  }

  return [
    'timeout',
    'terlalu lama',
    'tidak dapat terhubung',
    'respon server bukan json',
    'respon json server tidak valid',
    'kesalahan server',
    'gateway',
    'service unavailable',
    'temporarily unavailable',
    'proteksi hosting aktif',
  ].some((keyword) => msg.includes(keyword));
}

function tryRecoverHostingChallenge() {
  if (typeof window === 'undefined') {
    return;
  }

  try {
    const now = Date.now();
    const lastRaw = sessionStorage.getItem(HOSTING_CHALLENGE_RELOAD_KEY) || '0';
    const lastTs = Number(lastRaw || 0);
    if (Number.isFinite(lastTs) && lastTs > 0 && (now - lastTs) < HOSTING_CHALLENGE_RELOAD_COOLDOWN_MS) {
      return;
    }

    sessionStorage.setItem(HOSTING_CHALLENGE_RELOAD_KEY, String(now));
    window.location.reload();
  } catch {
    // Ignore sessionStorage/reload errors.
  }
}

function isMobileSuggestionViewport() {
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
    return IS_IOS_DEVICE;
  }

  return IS_IOS_DEVICE || window.matchMedia('(max-width: 860px)').matches;
}

function shouldAutoScrollSuggestionInput(inputEl) {
  if (!(inputEl instanceof HTMLElement) || !isMobileSuggestionViewport()) {
    return false;
  }

  const rect = inputEl.getBoundingClientRect();
  const visualViewport = typeof window !== 'undefined' ? window.visualViewport : null;
  const viewportHeight = Number(
    visualViewport?.height
    || window.innerHeight
    || document.documentElement?.clientHeight
    || 0
  );
  if (!Number.isFinite(viewportHeight) || viewportHeight <= 0) {
    return false;
  }

  const safeTop = Number(visualViewport?.offsetTop || 0) + 8;
  const preferredBottom = viewportHeight * 0.72;

  return rect.top < safeTop || rect.bottom > preferredBottom;
}

function hideCategorySuggestions(clearItems = false) {
  if (categorySuggestPanelEl) {
    categorySuggestPanelEl.classList.add('hidden');
    categorySuggestPanelEl.removeAttribute('data-mobile');
    clearSuggestionPanelPosition(categorySuggestPanelEl);
  }
  if (clearItems) {
    categorySuggestionItems = [];
    categorySuggestRenderKey = '';
    if (categorySuggestPanelEl) {
      categorySuggestPanelEl.innerHTML = '';
    }
  }
}

function hideServiceSuggestions(clearItems = false) {
  if (serviceSuggestPanelEl) {
    serviceSuggestPanelEl.classList.add('hidden');
    serviceSuggestPanelEl.removeAttribute('data-mobile');
    clearSuggestionPanelPosition(serviceSuggestPanelEl);
  }
  if (clearItems) {
    serviceSuggestionItems = [];
    serviceSuggestRenderKey = '';
    if (serviceSuggestPanelEl) {
      serviceSuggestPanelEl.innerHTML = '';
    }
  }
}

function hideGameServiceSuggestions(clearItems = false) {
  if (gameServiceSuggestPanelEl) {
    gameServiceSuggestPanelEl.classList.add('hidden');
    gameServiceSuggestPanelEl.removeAttribute('data-mobile');
    clearSuggestionPanelPosition(gameServiceSuggestPanelEl);
  }
  if (clearItems) {
    gameServiceSuggestionItems = [];
    gameSuggestRenderKey = '';
    if (gameServiceSuggestPanelEl) {
      gameServiceSuggestPanelEl.innerHTML = '';
    }
  }
}

function hideAllSuggestions(clearItems = false) {
  hideCategorySuggestions(clearItems);
  hideServiceSuggestions(clearItems);
  hideGameServiceSuggestions(clearItems);
}

function isSuggestionInteractiveTarget(target) {
  if (!(target instanceof Element)) {
    return false;
  }

  if (categorySuggestFieldEl?.contains(target) || serviceSuggestFieldEl?.contains(target) || gameSuggestFieldEl?.contains(target)) {
    return true;
  }

  if (
    categorySuggestPanelEl?.contains(target)
    || serviceSuggestPanelEl?.contains(target)
    || gameServiceSuggestPanelEl?.contains(target)
  ) {
    return true;
  }

  if (gameServiceInputEl?.contains?.(target)) {
    return true;
  }

  return false;
}

function keepSuggestionInputVisible(inputEl) {
  if (!inputEl || !isMobileSuggestionViewport()) {
    return;
  }

  setTimeout(() => {
    if (shouldAutoScrollSuggestionInput(inputEl)) {
      scrollElementIntoView(inputEl, 'center');
    }
    refreshOpenSuggestionPanelPosition();
  }, IS_IOS_DEVICE ? 110 : 70);
}

function clearSuggestionPanelPosition(panelEl) {
  if (!(panelEl instanceof HTMLElement)) {
    return;
  }

  panelEl.style.removeProperty('--suggest-top');
  panelEl.style.removeProperty('--suggest-max-height');
}

function positionSuggestionPanel(panelEl, inputEl) {
  if (!(panelEl instanceof HTMLElement)) {
    return;
  }

  if (!(inputEl instanceof HTMLElement) || !isMobileSuggestionViewport()) {
    clearSuggestionPanelPosition(panelEl);
    return;
  }

  const rect = inputEl.getBoundingClientRect();
  const visualViewport = typeof window !== 'undefined' ? window.visualViewport : null;
  const viewportOffsetTop = Number(visualViewport?.offsetTop || 0);
  const viewportHeight = Number(
    visualViewport?.height
    || window.innerHeight
    || document.documentElement?.clientHeight
    || 0
  );

  if (!Number.isFinite(viewportHeight) || viewportHeight <= 0) {
    clearSuggestionPanelPosition(panelEl);
    return;
  }

  const panelTop = Math.round(Math.max(viewportOffsetTop + 6, rect.bottom + 6));
  const availableHeight = Math.floor(viewportHeight - (panelTop - viewportOffsetTop) - 8);
  const panelMaxHeight = Math.max(120, Math.min(460, availableHeight));

  panelEl.style.setProperty('--suggest-top', `${panelTop}px`);
  panelEl.style.setProperty('--suggest-max-height', `${panelMaxHeight}px`);
}

function refreshOpenSuggestionPanelPosition() {
  if (!isMobileSuggestionViewport()) {
    return;
  }

  if (categorySuggestPanelEl && !categorySuggestPanelEl.classList.contains('hidden')) {
    positionSuggestionPanel(categorySuggestPanelEl, categoryInputEl);
  }

  if (serviceSuggestPanelEl && !serviceSuggestPanelEl.classList.contains('hidden')) {
    positionSuggestionPanel(serviceSuggestPanelEl, serviceInputEl);
  }

  if (gameServiceSuggestPanelEl && !gameServiceSuggestPanelEl.classList.contains('hidden')) {
    positionSuggestionPanel(gameServiceSuggestPanelEl, gameServiceInputEl);
  }
}

function hasOpenSuggestionPanel() {
  const categoryOpen = !!(categorySuggestPanelEl && !categorySuggestPanelEl.classList.contains('hidden'));
  const serviceOpen = !!(serviceSuggestPanelEl && !serviceSuggestPanelEl.classList.contains('hidden'));
  const gameOpen = !!(gameServiceSuggestPanelEl && !gameServiceSuggestPanelEl.classList.contains('hidden'));
  return categoryOpen || serviceOpen || gameOpen;
}

function renderCategorySuggestions(categories, rawInput = '') {
  if (!categorySuggestPanelEl || !categoryInputEl) {
    return;
  }

  const list = Array.isArray(categories) ? categories : [];
  categorySuggestionItems = list;

  const hasFocus = document.activeElement === categoryInputEl;
  if (!hasFocus || list.length === 0) {
    hideCategorySuggestions();
    return;
  }

  const query = normalizeQuery(rawInput);
  const signature = list.length > 0
    ? `${list.length}:${String(list[0] || '')}:${String(list[list.length - 1] || '')}`
    : '0';
  const renderKey = `${query}|${signature}`;
  if (categorySuggestRenderKey === renderKey && !categorySuggestPanelEl.classList.contains('hidden')) {
    return;
  }

  const itemsHtml = list.map((name, index) => `
    <button type="button" class="suggest-option" data-suggest-kind="category" data-suggest-index="${index}">
      <span class="suggest-main">${escapeHtml(String(name || ''))}</span>
    </button>
  `).join('');

  categorySuggestPanelEl.innerHTML = `
    <div class="suggest-header">Saran Kategori (${list.length})</div>
    <div class="suggest-list">${itemsHtml}</div>
  `;
  categorySuggestPanelEl.dataset.mobile = isMobileSuggestionViewport() ? '1' : '0';
  positionSuggestionPanel(categorySuggestPanelEl, categoryInputEl);
  categorySuggestPanelEl.classList.remove('hidden');
  categorySuggestRenderKey = renderKey;
}

function renderServiceSuggestions(services, rawInput = '') {
  if (!serviceSuggestPanelEl || !serviceInputEl) {
    return;
  }

  const list = Array.isArray(services) ? services : [];
  serviceSuggestionItems = list;

  const hasFocus = document.activeElement === serviceInputEl;
  if (!hasFocus || list.length === 0) {
    hideServiceSuggestions();
    return;
  }

  const query = normalizeQuery(rawInput);
  const signature = list.length > 0
    ? `${list.length}:${String(list[0]?.id || '')}:${String(list[list.length - 1]?.id || '')}`
    : '0';
  const renderKey = `${query}|${signature}`;
  if (serviceSuggestRenderKey === renderKey && !serviceSuggestPanelEl.classList.contains('hidden')) {
    return;
  }

  const itemsHtml = list.map((service, index) => {
    const label = serviceOptionLabel(service);
    const meta = [
      service?.category || 'Lainnya',
      `Harga/K ${rupiah(service?.sell_price ?? service?.sell_price_per_1000 ?? 0)}`,
      `Min ${formatInteger(service?.min || 0)}`,
      `Max ${formatInteger(service?.max || 0)}`,
    ].join(' | ');

    return `
      <button type="button" class="suggest-option" data-suggest-kind="service" data-suggest-index="${index}">
        <span class="suggest-main">${escapeHtml(label)}</span>
        <span class="suggest-meta">${escapeHtml(meta)}</span>
      </button>
    `;
  }).join('');

  serviceSuggestPanelEl.innerHTML = `
    <div class="suggest-header">Saran Layanan (${list.length})</div>
    <div class="suggest-list">${itemsHtml}</div>
  `;
  serviceSuggestPanelEl.dataset.mobile = isMobileSuggestionViewport() ? '1' : '0';
  positionSuggestionPanel(serviceSuggestPanelEl, serviceInputEl);
  serviceSuggestPanelEl.classList.remove('hidden');
  serviceSuggestRenderKey = renderKey;
}

function handleSuggestPanelClick(event, kind) {
  const target = event?.target;
  if (!(target instanceof Element)) return false;

  const optionEl = target.closest(`[data-suggest-kind="${kind}"]`);
  if (!optionEl) return false;

  const index = Number(optionEl.dataset.suggestIndex || -1);
  if (index < 0) return false;

  event.stopPropagation();

  if (kind === 'category') {
    applyCategorySuggestion(index);
  } else if (kind === 'service') {
    applyServiceSuggestion(index);
  } else if (kind === 'game-service') {
    applyGameServiceSuggestion(index);
  } else {
    return false;
  }

  return true;
}

function applyCategorySuggestion(index) {
  if (!categoryInputEl) {
    return;
  }

  const nextValue = categorySuggestionItems[index];
  if (!nextValue) {
    return;
  }

  categoryInputEl.value = String(nextValue);
  state.lastResolvedCategory = '';
  hideAllSuggestions();

  try {
    categoryInputEl.blur();
  } catch {
    // Ignore blur errors in old browsers.
  }

  fillCategoryOptions();
  fillServiceOptions({ force: true }).catch(() => {
    // Ignore service refresh errors after category selection.
  });
}

function applyServiceSuggestion(index) {
  if (!serviceInputEl) {
    return;
  }

  const service = serviceSuggestionItems[index];
  if (!service || typeof service !== 'object') {
    return;
  }

  state.selectedServiceId = Number(service.id || 0);
  serviceInputEl.value = serviceOptionLabel(service);
  if (categoryInputEl && service.category) {
    categoryInputEl.value = String(service.category);
  }
  hideAllSuggestions();

  try {
    serviceInputEl.blur();
  } catch {
    // Ignore blur errors in old browsers.
  }

  scheduleServiceInfoUpdate();
}

function applyGameServiceSuggestion(index) {
  if (!gameServiceInputEl) {
    return;
  }

  const service = gameServiceSuggestionItems[index];
  if (!service || typeof service !== 'object') {
    return;
  }

  applySelectedGameService(service);
  hideGameServiceSuggestions();

  try {
    gameServiceInputEl.blur();
  } catch {
    // Ignore blur errors in old browsers.
  }
}

function scheduleServiceInfoUpdate() {
  if (serviceInfoRafId) {
    return;
  }

  if (typeof requestAnimationFrame !== 'function') {
    setTimeout(() => {
      updateServiceInfo();
    }, 16);
    return;
  }

  serviceInfoRafId = requestAnimationFrame(() => {
    serviceInfoRafId = 0;
    updateServiceInfo();
  });
}

function scrollElementIntoView(el, block = 'start') {
  if (!el || typeof el.scrollIntoView !== 'function') {
    return;
  }

  const behavior = (IS_IOS_DEVICE || isMobileSuggestionViewport()) ? 'auto' : 'smooth';
  try {
    el.scrollIntoView({ behavior, block });
  } catch {
    el.scrollIntoView();
  }
}

function servicePriceNum(service) {
  const parsed = Number(service?.sell_price ?? service?.sell_price_per_1000 ?? 0);
  return Number.isFinite(parsed) ? parsed : 0;
}

function serviceIdNum(service) {
  const parsed = Number(service?.id ?? 0);
  return Number.isFinite(parsed) ? parsed : 0;
}

function serviceLocalRank(service, query, queryDigits = '') {
  const q = String(query || '').trim().toLowerCase();
  if (!q && !queryDigits) {
    return 10;
  }

  const id = String(service?.id || '');
  const nameLower = normalizeQuery(service?.name || '');
  const categoryLower = normalizeQuery(service?.category || '');
  const noteLower = normalizeQuery(service?.note || '');

  if (queryDigits && id === queryDigits) return 0;
  if (queryDigits && id.startsWith(queryDigits)) return 1;
  if (q && nameLower.startsWith(q)) return 2;
  if (q && nameLower.includes(q)) return 3;
  if (q && categoryLower.includes(q)) return 4;
  if (q && noteLower.includes(q)) return 5;
  return 99;
}

function sortServicesByRankAndPrice(services, query, queryDigits = '') {
  return [...services].sort((a, b) => {
    const rankA = serviceLocalRank(a, query, queryDigits);
    const rankB = serviceLocalRank(b, query, queryDigits);
    if (rankA !== rankB) {
      return rankA - rankB;
    }

    const priceDiff = servicePriceNum(a) - servicePriceNum(b);
    if (priceDiff !== 0) {
      return priceDiff;
    }

    const nameCompare = ID_COLLATOR.compare(String(a?.name || ''), String(b?.name || ''));
    if (nameCompare !== 0) {
      return nameCompare;
    }

    return serviceIdNum(a) - serviceIdNum(b);
  });
}

function clonePayload(data) {
  if (typeof structuredClone === 'function') {
    try {
      return structuredClone(data);
    } catch {
      // Fallback to JSON clone for plain objects.
    }
  }

  try {
    return JSON.parse(JSON.stringify(data));
  } catch {
    return data;
  }
}

function trimApiCacheIfNeeded() {
  if (apiGetCache.size <= API_GET_CACHE_MAX_ITEMS) {
    return;
  }

  const entries = Array.from(apiGetCache.entries())
    .sort((a, b) => (a[1]?.storedAt || 0) - (b[1]?.storedAt || 0));
  const removeCount = Math.max(1, entries.length - API_GET_CACHE_MAX_ITEMS);

  for (let i = 0; i < removeCount; i += 1) {
    apiGetCache.delete(entries[i][0]);
  }
}

function ensureAdminToastContainer() {
  if (adminToastContainerEl && document.body.contains(adminToastContainerEl)) {
    return adminToastContainerEl;
  }

  const existing = document.getElementById('adminToastStack');
  if (existing) {
    adminToastContainerEl = existing;
    return adminToastContainerEl;
  }

  const container = document.createElement('div');
  container.id = 'adminToastStack';
  container.className = 'toast-stack';
  document.body.appendChild(container);
  adminToastContainerEl = container;
  return container;
}

function showAdminToast(message, type = 'info', durationMs = 5000) {
  const container = ensureAdminToastContainer();
  const toast = document.createElement('div');
  toast.className = `toast-item ${type}`;
  toast.textContent = String(message || '').trim() || 'Notifikasi admin';
  container.appendChild(toast);

  requestAnimationFrame(() => {
    toast.classList.add('show');
  });

  const remove = () => {
    toast.classList.remove('show');
    setTimeout(() => {
      toast.remove();
    }, 220);
  };

  const timeout = Math.max(2200, Number(durationMs || 5000));
  setTimeout(remove, timeout);
}

function requestBrowserNotificationPermission() {
  if (adminNotificationPermissionAsked) {
    return;
  }
  if (typeof window === 'undefined' || typeof Notification === 'undefined') {
    return;
  }
  if (Notification.permission !== 'default') {
    adminNotificationPermissionAsked = true;
    return;
  }

  adminNotificationPermissionAsked = true;
  Notification.requestPermission().catch(() => {
    // Ignore permission errors.
  });
}

function pushBrowserNotification(title, body) {
  if (typeof window === 'undefined' || typeof Notification === 'undefined') {
    return;
  }
  if (Notification.permission !== 'granted') {
    return;
  }

  try {
    const logoPath = resolveAssetPath(pageEl?.dataset?.logoPath || 'assets/logo.png');
    const notification = new Notification(String(title || 'Notifikasi Admin'), {
      body: String(body || ''),
      icon: logoPath,
      badge: logoPath,
      tag: 'odyssiavault-admin-pending',
      renotify: true,
    });

    setTimeout(() => {
      try {
        notification.close();
      } catch {
        // Ignore close errors.
      }
    }, 9000);
  } catch {
    // Ignore Notification API runtime errors.
  }
}

function updateAdminMenuLabel() {
  if (!adminMenuLinkEl) return;

  const baseLabel = String(adminMenuLinkEl.dataset.baseLabel || adminMenuLinkEl.textContent || 'Admin Panel').trim() || 'Admin Panel';
  adminMenuLinkEl.dataset.baseLabel = baseLabel;

  const isAdmin = String(state.user?.role || '') === 'admin';
  const pendingCount = isAdmin ? Math.max(0, Number(state.adminPaymentOrders?.length || 0)) : 0;
  adminMenuLinkEl.textContent = pendingCount > 0 ? `${baseLabel} (${pendingCount})` : baseLabel;
  adminMenuLinkEl.classList.toggle('has-badge', pendingCount > 0);
}

function handleAdminPendingNotifications(pendingOrders) {
  const orders = Array.isArray(pendingOrders) ? pendingOrders : [];
  const currentIds = new Set(
    orders
      .map((item) => Number(item?.id || 0))
      .filter((id) => Number.isFinite(id) && id > 0)
  );

  if (!adminPendingInitialized) {
    adminSeenPendingOrderIds = currentIds;
    adminPendingInitialized = true;
    return;
  }

  const newOrders = orders.filter((item) => {
    const id = Number(item?.id || 0);
    return id > 0 && !adminSeenPendingOrderIds.has(id);
  });

  adminSeenPendingOrderIds = currentIds;
  if (!newOrders.length) {
    return;
  }

  const newest = newOrders[0] || {};
  const count = newOrders.length;
  const headline = count === 1
    ? `Order baru #${newest.id} menunggu konfirmasi admin`
    : `${count} order baru menunggu konfirmasi admin`;
  const detail = count === 1
    ? `${newest.username ? `@${newest.username} | ` : ''}${newest.service_name || 'Layanan'}`
    : 'Buka Admin Panel untuk cek dan verifikasi pembayaran.';

  showAdminToast(headline, 'info', 6800);
  if (adminPaymentNoticeEl && state.currentView === 'admin') {
    showNotice(adminPaymentNoticeEl, 'info', `${headline}\n${detail}`);
  }
  pushBrowserNotification('Notifikasi Odyssiavault', `${headline}${detail ? ` - ${detail}` : ''}`);
}

function stopAdminPendingPoller() {
  if (adminPendingPollTimer) {
    clearInterval(adminPendingPollTimer);
    adminPendingPollTimer = null;
  }
  adminPendingInitialized = false;
  adminSeenPendingOrderIds = new Set();
}

function isPakasirGatewayAutomationEnabled() {
  return PAYMENT_GATEWAY_ENABLED && PAYMENT_GATEWAY_PROVIDER === 'pakasir';
}

function stopPendingPaymentSyncPoller() {
  if (pendingPaymentSyncTimer) {
    clearInterval(pendingPaymentSyncTimer);
    pendingPaymentSyncTimer = null;
  }
  pendingPaymentSyncInFlight = false;
  pendingPaymentSyncLastRunAt = 0;
}

function collectPendingSyncOrderIds(isAdmin) {
  const source = isAdmin
    ? (Array.isArray(state.adminPaymentOrders) ? state.adminPaymentOrders : [])
    : (Array.isArray(state.orders) ? state.orders : []);

  const ids = [];
  for (const item of source) {
    if (!item || typeof item !== 'object') continue;
    const id = Number(item.id || 0);
    if (!Number.isFinite(id) || id <= 0) continue;

    const normalizedStatus = normalizeOrderStatus(item);
    if (normalizedStatus !== 'Menunggu Pembayaran') continue;

    const hasProviderOrderId = String(item.provider_order_id || '').trim() !== '';
    if (hasProviderOrderId) continue;

    const paymentMethod = String(item.payment_method || '').toLowerCase();
    const paymentChannel = String(item.payment_channel_name || '').toLowerCase();
    const isPakasirOrder = paymentMethod === 'pakasir' || paymentChannel.includes('pakasir');
    if (!isPakasirOrder) continue;

    ids.push(id);
    if (ids.length >= PENDING_PAYMENT_SYNC_MAX_ORDERS) {
      break;
    }
  }

  return ids;
}

async function syncPendingPaymentOrders(options = {}) {
  if (!isPakasirGatewayAutomationEnabled()) return;

  const silent = !!options.silent;
  const detectProgress = !!options.detectProgress;
  if (!state.user) return;
  if (pendingPaymentSyncInFlight) return;

  const now = Date.now();
  if ((now - pendingPaymentSyncLastRunAt) < PENDING_PAYMENT_SYNC_MIN_GAP_MS) {
    return;
  }

  const isAdmin = String(state.user?.role || '') === 'admin';
  pendingPaymentSyncInFlight = true;
  pendingPaymentSyncLastRunAt = now;

  try {
    if (isAdmin) {
      if (!state.adminPaymentOrdersLoaded) {
        await loadAdminPaymentOrders({ force: true, silent: true, detectNew: false });
      }
    } else if (!state.ordersLoaded) {
      await loadOrders({ force: false });
    }

    const orderIds = collectPendingSyncOrderIds(isAdmin);
    if (!orderIds.length) return;

    const changedOrders = [];
    for (const orderId of orderIds) {
      const { data } = await apiRequest('./api/order_status.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order_id: orderId }),
      });

      if (!data?.status) {
        continue;
      }

      const normalizedStatus = String(data?.data?.status || '').trim() || 'Menunggu Pembayaran';
      if (normalizedStatus !== 'Menunggu Pembayaran') {
        changedOrders.push({
          id: orderId,
          status: normalizedStatus,
          msg: String(data?.data?.msg || '').trim(),
        });
      }

      await waitMs(100);
    }

    if (!changedOrders.length) {
      return;
    }

    await Promise.all([
      fetchSession({ attempts: SESSION_FETCH_RETRY_ATTEMPTS, softFail: true }),
      loadOrders({ force: true }),
      isAdmin ? loadAdminPaymentOrders({ force: true, silent: true, detectNew: true }) : Promise.resolve(),
      loadTop5Services({ force: true }),
    ]);
    updateHeaderStats();
    updateAdminMenuLabel();

    if (!silent) {
      const firstChanged = changedOrders[0];
      const infoMsg = firstChanged?.msg
        || `Order #${firstChanged?.id || '-'} otomatis ter-update ke status ${firstChanged?.status || '-'}.`;
      if (isAdmin && adminPaymentNoticeEl && state.currentView === 'admin') {
        showNotice(adminPaymentNoticeEl, 'ok', infoMsg);
      } else if (ordersNotice && state.currentView === 'purchase') {
        showNotice(ordersNotice, 'ok', infoMsg);
      }
    }

    if (detectProgress && isAdmin && changedOrders.length > 0) {
      const firstChanged = changedOrders[0];
      showAdminToast(
        `Order #${firstChanged.id} ter-update ke ${firstChanged.status}`,
        'ok',
        5200
      );
    }
  } finally {
    pendingPaymentSyncInFlight = false;
  }
}

function startPendingPaymentSyncPoller() {
  if (!isPakasirGatewayAutomationEnabled()) {
    stopPendingPaymentSyncPoller();
    return;
  }

  if (!state.user) {
    stopPendingPaymentSyncPoller();
    return;
  }
  if (pendingPaymentSyncTimer) {
    return;
  }

  syncPendingPaymentOrders({ silent: true, detectProgress: false }).catch(() => {
    // Ignore first sync error.
  });

  pendingPaymentSyncTimer = setInterval(() => {
    if (document.hidden || !state.user) {
      return;
    }
    syncPendingPaymentOrders({ silent: true, detectProgress: true }).catch(() => {
      // Ignore background sync error.
    });
  }, PENDING_PAYMENT_SYNC_POLL_MS);
}

function startAdminPendingPoller() {
  const isAdmin = String(state.user?.role || '') === 'admin';
  if (!isAdmin) {
    stopAdminPendingPoller();
    return;
  }

  requestBrowserNotificationPermission();
  if (adminPendingPollTimer) {
    return;
  }

  adminPendingInitialized = false;
  loadAdminPaymentOrders({ force: true, silent: true, detectNew: false }).catch(() => {
    // Ignore first background polling error.
  });

  adminPendingPollTimer = setInterval(() => {
    if (document.hidden) {
      return;
    }
    Promise.all([
      loadAdminPaymentOrders({ force: true, silent: true, detectNew: true }),
      syncPendingPaymentOrders({ silent: true, detectProgress: true }),
    ]).catch(() => {
      // Ignore background polling/sync error.
    });
  }, ADMIN_PENDING_POLL_MS);
}

function sanitizeNewsSourceName(value) {
  const sourceName = String(value || '').trim();
  if (!sourceName) {
    return NEWS_SOURCE_BRAND;
  }

  return sourceName;
}

function sanitizeNewsSourceUrl(value) {
  const sourceUrl = String(value || '').trim();
  return sourceUrl;
}

function buildServiceIndex() {
  const byCategory = new Map();
  const byCategorySortedByPrice = new Map();
  const byServiceId = new Map();
  const byProviderCategoryId = new Map();
  const categoryProviderId = new Map();
  const byExactName = new Map();
  const byOptionLabel = new Map();

  const toNumber = (value, fallback = 0) => {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : fallback;
  };

  state.services.forEach((service) => {
    const category = service?.category || 'Lainnya';
    const serviceId = toNumber(service?.id, 0);
    const providerCategoryId = toNumber(service?.provider_cat_id, 0);
    const pricePer1000 = toNumber(service?.sell_price_per_1000 ?? service?.sell_price, 0);
    const minValue = toNumber(service?.min, 0);
    const maxValue = toNumber(service?.max, 0);
    const normalizedName = normalizeQuery(service?.name || '');
    const normalizedCategory = normalizeQuery(category);
    const normalizedNote = normalizeQuery(service?.note || '');
    const searchableText = `${serviceId} ${normalizedName} ${normalizedCategory} ${normalizedNote}`.trim();

    // Cache numeric/text values once so heavy list operations don't re-parse on each input.
    service.__idNum = serviceId;
    service.__providerCategoryIdNum = providerCategoryId;
    service.__priceNum = pricePer1000;
    service.__minNum = minValue;
    service.__maxNum = maxValue;
    service.__nameNorm = normalizedName;
    service.__categoryNorm = normalizedCategory;
    service.__searchNorm = searchableText;

    if (!byCategory.has(category)) {
      byCategory.set(category, []);
    }
    byCategory.get(category).push(service);

    if (serviceId > 0 && !byServiceId.has(serviceId)) {
      byServiceId.set(serviceId, service);
    }

    if (providerCategoryId > 0 && !byProviderCategoryId.has(providerCategoryId)) {
      byProviderCategoryId.set(providerCategoryId, category);
    }

    if (providerCategoryId > 0 && !categoryProviderId.has(category)) {
      categoryProviderId.set(category, providerCategoryId);
    }
  });

  const categories = [...byCategory.keys()]
    .sort((a, b) => ID_COLLATOR.compare(String(a), String(b)));

  categories.forEach((category) => {
    const categoryServices = [...(byCategory.get(category) || [])];
    categoryServices.sort((a, b) => {
      if ((a.__priceNum || 0) !== (b.__priceNum || 0)) {
        return (a.__priceNum || 0) - (b.__priceNum || 0);
      }

      const nameCompare = ID_COLLATOR.compare(String(a?.name || ''), String(b?.name || ''));
      if (nameCompare !== 0) {
        return nameCompare;
      }

      return (a.__idNum || 0) - (b.__idNum || 0);
    });
    byCategorySortedByPrice.set(category, categoryServices);
  });

  const globalSortedByPrice = [...state.services].sort((a, b) => {
    if ((a.__priceNum || 0) !== (b.__priceNum || 0)) {
      return (a.__priceNum || 0) - (b.__priceNum || 0);
    }

    const nameCompare = ID_COLLATOR.compare(String(a?.name || ''), String(b?.name || ''));
    if (nameCompare !== 0) {
      return nameCompare;
    }

    return (a.__idNum || 0) - (b.__idNum || 0);
  });

  globalSortedByPrice.forEach((service) => {
    const key = String(service.__nameNorm || '');
    if (!key || byExactName.has(key)) {
      // Continue to capture option labels even when exact-name index already exists.
    } else {
      byExactName.set(key, service);
    }

    const optionLabelKey = normalizeQuery(serviceOptionLabel(service));
    if (optionLabelKey && !byOptionLabel.has(optionLabelKey)) {
      byOptionLabel.set(optionLabelKey, service);
    }
  });

  const categoryEntries = categories.map((name) => {
    const providerIdNum = Number(categoryProviderId.get(name) || 0);
    const providerIdText = providerIdNum > 0 ? String(providerIdNum) : '';
    return {
      name,
      norm: normalizeQuery(name),
      providerIdNum,
      providerIdText,
      searchable: `${normalizeQuery(name)} ${providerIdText}`.trim(),
    };
  });

  state.serviceIndex = {
    categories,
    categoryEntries,
    byCategory,
    byCategorySortedByPrice,
    byServiceId,
    byProviderCategoryId,
    categoryProviderId,
    globalSortedByPrice,
    byExactName,
    byOptionLabel,
  };
}

function buildServiceDirectory(categories) {
  const normalized = Array.isArray(categories) ? categories : [];
  const byProviderCategoryId = new Map();
  const seenNames = new Set();
  const entries = [];

  normalized.forEach((category) => {
    const name = String(category?.name || '').trim();
    if (!name || seenNames.has(name)) {
      return;
    }
    seenNames.add(name);

    const providerIdNum = Number(category?.provider_cat_id || 0);
    const providerIdText = providerIdNum > 0 ? String(providerIdNum) : '';

    if (providerIdNum > 0 && !byProviderCategoryId.has(providerIdNum)) {
      byProviderCategoryId.set(providerIdNum, name);
    }

    entries.push({
      name,
      norm: normalizeQuery(name),
      providerIdNum,
      providerIdText,
      searchable: `${normalizeQuery(name)} ${providerIdText}`.trim(),
    });
  });

  entries.sort((a, b) => ID_COLLATOR.compare(a.name, b.name));

  state.serviceDirectory = {
    categories: entries.map((item) => item.name),
    categoryEntries: entries,
    byProviderCategoryId,
  };
}

function normalizePanelView(value) {
  const normalized = String(value || '').trim().toLowerCase();
  const mapped = LEGACY_VIEW_MAP[normalized] || normalized;
  return PANEL_VIEWS.has(mapped) ? mapped : 'utama';
}

function topbarTitleByView(view) {
  const normalized = normalizePanelView(view);
  const map = {
    utama: 'Halaman Utama Odyssiavault',
    dashboard: 'Dashboard Odyssiavault',
    profile: 'Profil Akun',
    top5: 'Top 5 Layanan',
    purchase: 'Buat Pesanan',
    refill: 'Refill Pesanan',
    game: 'Topup Game',
    deposit: 'Deposit',
    ticket: 'Tiket Bantuan',
    services: 'Daftar Layanan',
    pages: 'Halaman Informasi',
    admin: 'Admin Panel',
  };

  return map[normalized] || 'Odyssiavault';
}

function updateUrlForView(view) {
  const normalized = normalizePanelView(view);
  if (!window?.history || !window?.location) return;

  try {
    const url = new URL(window.location.href);
    url.searchParams.set('page', normalized);
    url.hash = '';
    window.history.replaceState({}, '', `${url.pathname}${url.search}`);
  } catch {
    // Ignore URL replace errors in unsupported environments.
  }
}

function normalizeLines(value) {
  return String(value || '')
    .replace(/\r\n/g, '\n')
    .replace(/\r/g, '\n')
    .split('\n')
    .map((line) => line.trim())
    .filter(Boolean);
}

function matchRank(text, query) {
  if (!query) return 0;
  const normalized = String(text || '').toLowerCase();
  if (normalized.startsWith(query)) return 0;
  if (normalized.includes(query)) return 1;
  return 2;
}

function prioritizeByQuery(items, query, getText) {
  if (!query) return [...items];

  return [...items].sort((a, b) => {
    const rankA = matchRank(getText(a), query);
    const rankB = matchRank(getText(b), query);

    if (rankA !== rankB) return rankA - rankB;

    return String(getText(a)).localeCompare(String(getText(b)), 'id', { sensitivity: 'base' });
  });
}

function serviceText(service) {
  return `${service?.name || ''} ${service?.note || ''} ${service?.category || ''} ${service?.type || ''}`.toLowerCase();
}

function isMentionsCustomListService(service) {
  const combined = serviceText(service);
  return ['mentions custom list', 'mention custom', 'custom list', 'mentions', 'usernames']
    .some((keyword) => combined.includes(keyword));
}

function isCommentRepliesService(service) {
  const combined = serviceText(service);
  return ['comment replies', 'comment reply', 'replies', 'reply']
    .some((keyword) => combined.includes(keyword));
}

function isCommentService(service) {
  if (!service) return false;

  const combined = serviceText(service);
  const isCommentLike = combined.includes('commentlike') || combined.includes('comment like');
  if (isCommentLike) return false;

  return ['comment', 'komen', 'komentar'].some((keyword) => combined.includes(keyword))
    || isCommentRepliesService(service);
}

function selectedServiceQuantity(service) {
  if (!service) {
    return 0;
  }

  if (isMentionsCustomListService(service)) {
    const usernamesValue = String(usernamesEl?.value || '');
    const komenValue = String(komenEl?.value || '');
    const source = usernamesValue.trim() !== '' ? usernamesValue : komenValue;
    return normalizeLines(source).length;
  }

  if (isCommentService(service)) {
    const komenValue = String(komenEl?.value || '');
    const commentsValue = String(commentsEl?.value || '');
    const source = isCommentRepliesService(service)
      ? (commentsValue.trim() !== '' ? commentsValue : komenValue)
      : (komenValue.trim() !== '' ? komenValue : commentsValue);
    return normalizeLines(source).length;
  }

  return Number((quantityEl?.value || '0').replace(/\D+/g, '')) || 0;
}

function showNotice(el, type, message) {
  if (!el) return;
  el.className = `notice ${type}`;
  el.textContent = message;
  el.classList.remove('hidden');
}

function hideNotice(el) {
  if (!el) return;
  el.classList.add('hidden');
  el.textContent = '';
}

function setAccountMenuOpen(isOpen) {
  if (!accountMenuToggleEl || !accountMenuPanelEl) return;
  accountMenuToggleEl.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
  accountMenuPanelEl.classList.toggle('hidden', !isOpen);
}

function closeAccountMenu() {
  setAccountMenuOpen(false);
}

function ticketStatusLabel(status) {
  const key = String(status || '').toLowerCase();
  if (key === 'open') return 'Open';
  if (key === 'answered') return 'Answered';
  if (key === 'closed') return 'Closed';
  return key ? key : '-';
}

function ticketStatusClass(status) {
  const key = String(status || '').toLowerCase();
  if (key === 'open') return 's-processing';
  if (key === 'answered') return 's-completed';
  if (key === 'closed') return 's-failed';
  return 's-other';
}

function ticketPriorityLabel(priority) {
  const key = String(priority || '').toLowerCase();
  if (key === 'urgent') return 'Urgent';
  if (key === 'high') return 'High';
  if (key === 'low') return 'Low';
  return 'Normal';
}

function userInitials(username) {
  const clean = String(username || '').replace(/[^a-zA-Z0-9_ ]/g, ' ').trim();
  if (!clean) return 'OV';

  const parts = clean.split(/[\s_]+/).filter(Boolean);
  if (parts.length >= 2) {
    return `${parts[0][0] || ''}${parts[1][0] || ''}`.toUpperCase();
  }

  return clean.slice(0, 2).toUpperCase();
}

function syncLoginPasswordToggleState() {
  if (!loginPasswordEl || !loginPasswordToggleEl) return;

  const isVisible = loginPasswordEl.type === 'text';
  loginPasswordToggleEl.textContent = isVisible ? 'Sembunyikan' : 'Lihat';
  loginPasswordToggleEl.setAttribute('aria-pressed', isVisible ? 'true' : 'false');
  loginPasswordToggleEl.setAttribute('aria-label', isVisible ? 'Sembunyikan password' : 'Lihat password');
}

async function apiRequest(url, options = {}) {
  const {
    timeoutMs = API_REQUEST_TIMEOUT_MS,
    cacheTtlMs = API_GET_CACHE_TTL_MS,
    forceRefresh = false,
    ...rawOptions
  } = options;
  const method = String(rawOptions.method || 'GET').toUpperCase();
  const requestUrl = String(url || '');
  const hasTimestampBypass = requestUrl.includes('_t=');
  const canUseCache = method === 'GET' && !forceRefresh && !hasTimestampBypass;
  const cacheKey = requestUrl;
  const now = Date.now();

  if (canUseCache) {
    const cached = apiGetCache.get(cacheKey);
    if (cached && Number(cached.expiresAt || 0) > now) {
      return {
        response: null,
        data: clonePayload(cached.data),
        fromCache: true,
      };
    }
    if (cached) {
      apiGetCache.delete(cacheKey);
    }
  }

  if (canUseCache && apiInFlight.has(cacheKey)) {
    const inFlight = await apiInFlight.get(cacheKey);
    return {
      response: inFlight.response || null,
      data: clonePayload(inFlight.data),
      fromCache: Boolean(inFlight.fromCache),
    };
  }

  const runRequest = async () => {
    const controller = typeof AbortController !== 'undefined' ? new AbortController() : null;
    const timeoutId = controller
      ? setTimeout(() => {
        try {
          controller.abort();
        } catch {
          // Ignore abort errors.
        }
      }, Math.max(3000, Number(timeoutMs || API_REQUEST_TIMEOUT_MS)))
      : null;

    const mergedHeaders = {
      Accept: 'application/json',
      ...(rawOptions.headers || {}),
    };
    const requestOptions = {
      credentials: 'same-origin',
      ...rawOptions,
      headers: mergedHeaders,
    };
    if (controller) {
      requestOptions.signal = controller.signal;
    }

    let response;
    try {
      response = await fetch(url, requestOptions);
    } catch (error) {
      if (timeoutId) {
        clearTimeout(timeoutId);
      }

      const isTimeout = !!(error && (error.name === 'AbortError' || String(error.message || '').toLowerCase().includes('abort')));
      const msg = isTimeout
        ? 'Permintaan ke server terlalu lama (timeout). Silakan coba lagi.'
        : 'Tidak dapat terhubung ke server.';

      return {
        response: null,
        data: { status: false, data: { msg } },
      };
    }
    if (timeoutId) {
      clearTimeout(timeoutId);
    }

    const statusCode = Number(response.status || 0);
    let rawBody = '';
    try {
      rawBody = await response.text();
    } catch {
      rawBody = '';
    }

    let data;
    try {
      data = rawBody ? JSON.parse(rawBody) : null;
    } catch {
      data = null;
    }

    if ((!data || typeof data !== 'object') && rawBody) {
      const trimmedBody = rawBody.trim();
      const jsonStart = trimmedBody.indexOf('{');
      const jsonEnd = trimmedBody.lastIndexOf('}');
      if (jsonStart >= 0 && jsonEnd > jsonStart) {
        const candidate = trimmedBody.slice(jsonStart, jsonEnd + 1);
        try {
          const recovered = JSON.parse(candidate);
          if (recovered && typeof recovered === 'object') {
            data = recovered;
          }
        } catch {
          // Keep original parse failure.
        }
      }
    }

    if (!data || typeof data !== 'object') {
      const contentType = String(response.headers.get('content-type') || '').toLowerCase();
      const bodyTrimmed = rawBody.trim();
      const looksHtml = contentType.includes('text/html') || bodyTrimmed.startsWith('<!doctype') || bodyTrimmed.startsWith('<html');
      const looksHostingChallenge = bodyTrimmed.includes('/aes.js') && bodyTrimmed.includes('__test=');
      if (looksHostingChallenge) {
        tryRecoverHostingChallenge();
      }
      data = {
        status: false,
        data: {
          msg: looksHostingChallenge
            ? 'Proteksi hosting aktif. Muat ulang halaman lalu coba lagi.'
            : (
              looksHtml
                ? `Respon server bukan JSON (HTTP ${statusCode || '-'}).`
                : `Respon JSON server tidak valid (HTTP ${statusCode || '-'}).`
            ),
        },
        challenge: looksHostingChallenge,
      };
    }

    if (canUseCache && data?.status === true) {
      apiGetCache.set(cacheKey, {
        data: clonePayload(data),
        storedAt: Date.now(),
        expiresAt: Date.now() + Math.max(1000, Number(cacheTtlMs || API_GET_CACHE_TTL_MS)),
      });
      trimApiCacheIfNeeded();
    }

    if (method !== 'GET' && data?.status === true) {
      apiGetCache.clear();
    }

    return { response, data };
  };

  const executeWithRetry = async () => {
    const maxAttempts = canUseCache ? 2 : 1;
    let payload = null;
    for (let attempt = 1; attempt <= maxAttempts; attempt += 1) {
      payload = await runRequest();
      if (payload?.data?.status === true) {
        return payload;
      }
      if (attempt >= maxAttempts || !isRetryableApiFailure(payload?.data)) {
        return payload;
      }
      await waitMs(180 * attempt);
    }
    return payload || { response: null, data: { status: false, data: { msg: 'Permintaan gagal.' } } };
  };

  if (canUseCache) {
    const promise = executeWithRetry()
      .then((payload) => ({
        response: payload.response || null,
        data: payload.data,
        fromCache: false,
      }))
      .finally(() => {
        apiInFlight.delete(cacheKey);
      });
    apiInFlight.set(cacheKey, promise);
    return promise;
  }

  return executeWithRetry();
}

function switchAuthTab(tab) {
  const loginMode = tab === 'login';
  if (tabLogin) tabLogin.classList.toggle('active', loginMode);
  if (tabRegister) tabRegister.classList.toggle('active', !loginMode);
  if (loginForm) loginForm.classList.toggle('hidden', !loginMode);
  if (registerForm) registerForm.classList.toggle('hidden', loginMode);
  hideNotice(authNotice);
}

function setViewLoggedIn(isLoggedIn) {
  if (authView) authView.classList.toggle('hidden', isLoggedIn);
  if (appView) appView.classList.toggle('hidden', !isLoggedIn);
  updateCsChatWidgetVisibility();
  if (!isLoggedIn) {
    closeAccountMenu();
    hideAllSuggestions(true);
    stopCsChatPoller();
    stopCsChatAdminPoller();
  }
}

function syncTop5Visibility() {
  if (!top5SectionEl || !top5EmptyStateEl) {
    return;
  }

  if (state.currentView !== 'top5') {
    top5SectionEl.classList.add('hidden');
    top5EmptyStateEl.classList.add('hidden');
    return;
  }

  if (state.hasTop5Data) {
    top5SectionEl.classList.remove('hidden');
    top5EmptyStateEl.classList.add('hidden');
  } else {
    top5SectionEl.classList.add('hidden');
    top5EmptyStateEl.classList.remove('hidden');
  }
}

function applyPanelView(view) {
  const isAdmin = String(state.user?.role || '') === 'admin';
  const requestedView = normalizePanelView(view);
  const resolvedView = (!isAdmin && requestedView === 'admin') ? 'utama' : requestedView;
  closeAccountMenu();
  hideAllSuggestions();

  if (requestedView !== resolvedView) {
    updateUrlForView(resolvedView);
  }

  state.currentView = resolvedView;

  if (adminMenuLinkEl) {
    adminMenuLinkEl.classList.toggle('hidden', !isAdmin);
  }
  updateAdminMenuLabel();

  panelNavLinks.forEach((link) => {
    const isActive = (link.dataset.view || '') === state.currentView;
    link.classList.toggle('active', isActive);
  });

  viewSections.forEach((section) => {
    const map = String(section.dataset.viewSection || '')
      .split(',')
      .map((item) => item.trim())
      .filter(Boolean);
    const shouldShow = map.includes(state.currentView);
    section.classList.toggle('hidden', !shouldShow);
  });

  syncTop5Visibility();

  if (!isAdmin && newsAdminSectionEl) {
    newsAdminSectionEl.classList.add('hidden');
  }

  if (topbarTitleEl) {
    topbarTitleEl.textContent = topbarTitleByView(state.currentView);
  }

  updateHeaderStats();
}

function updateHeaderStats() {
  if (!state.user) {
    if (welcomeText) {
      welcomeText.textContent = 'Memuat akun buyer...';
    }
    if (statBalance) {
      statBalance.textContent = '0';
    }
    if (statOrders) {
      statOrders.textContent = '0';
    }
    if (statSpent) {
      statSpent.textContent = '0';
    }
    return;
  }

  const displayName = state.user.username || 'buyer';
  const currentView = normalizePanelView(state.currentView);
  if (welcomeText) {
    if (currentView === 'utama') {
      welcomeText.textContent = `Halo @${displayName}. Pilih dulu jalur belanja kamu: SSM atau Topup Game.`;
    } else {
      welcomeText.textContent = `Halo @${displayName}. Pilih layanan, checkout, bayar langsung, lalu konfirmasi pembayaran.`;
    }
  }
  if (statBalance) {
    statBalance.textContent = String(state.stats.waiting_orders || 0);
  }
  if (statOrders) {
    statOrders.textContent = String(state.stats.processing_orders || 0);
  }
  if (statSpent) {
    statSpent.textContent = String(state.stats.completed_orders || 0);
  }
}

function updateAccountMenu() {
  const user = state.user || null;
  const username = user?.username ? `@${user.username}` : '@guest';
  const role = user?.role ? String(user.role) : 'guest';

  if (accountAvatarEl) {
    accountAvatarEl.textContent = userInitials(user?.username || '');
  }
  if (accountMenuNameEl) {
    accountMenuNameEl.textContent = username;
    accountMenuNameEl.title = username;
  }
  if (accountMenuRoleEl) {
    accountMenuRoleEl.textContent = role;
  }
}

function updateProfilePanel() {
  const user = state.user || null;
  const stats = state.stats || {};

  if (profileUsernameEl) profileUsernameEl.textContent = user?.username ? `@${user.username}` : '-';
  if (profileRoleEl) profileRoleEl.textContent = user?.role || '-';
  if (profileEmailEl) profileEmailEl.textContent = user?.email || '-';
  if (profileCreatedAtEl) profileCreatedAtEl.textContent = user?.created_at ? formatDateTime(user.created_at) : '-';
  if (profileLastLoginAtEl) profileLastLoginAtEl.textContent = user?.last_login_at ? formatDateTime(user.last_login_at) : '-';
  if (profileTotalSpentEl) profileTotalSpentEl.textContent = rupiah(stats.total_spent || 0);
  if (profileTotalOrdersEl) profileTotalOrdersEl.textContent = formatInteger(stats.total_orders || 0);
  if (profileWaitingOrdersEl) profileWaitingOrdersEl.textContent = formatInteger(stats.waiting_orders || 0);
  if (profileProcessingOrdersEl) profileProcessingOrdersEl.textContent = formatInteger(stats.processing_orders || 0);
  if (profileCompletedOrdersEl) profileCompletedOrdersEl.textContent = formatInteger(stats.completed_orders || 0);
}

function setPanelInfoClosed(closed) {
  state.panelInfoClosed = !!closed;
  if (panelInfoSectionEl) {
    panelInfoSectionEl.classList.toggle('collapsed', state.panelInfoClosed);
  }
  if (panelInfoCloseBtnEl) {
    panelInfoCloseBtnEl.textContent = state.panelInfoClosed ? 'Buka' : 'Tutup';
  }

  try {
    if (state.panelInfoClosed) {
      sessionStorage.setItem(PANEL_INFO_STORAGE_KEY, '1');
    } else {
      sessionStorage.removeItem(PANEL_INFO_STORAGE_KEY);
    }
  } catch {
    // Ignore browser storage errors.
  }
}

function restorePanelInfoState() {
  let closed = false;
  try {
    closed = sessionStorage.getItem(PANEL_INFO_STORAGE_KEY) === '1';
  } catch {
    closed = false;
  }

  setPanelInfoClosed(closed);
}

function renderPanelInfoTicker() {
  if (!panelInfoTickerTextEl) return;

  const meta = state.panelInfoMeta || {};
  const messages = [];

  const totalServices = Number(meta.total_services || 0);
  const totalCategories = Number(meta.total_categories || 0);
  if (totalServices > 0) {
    messages.push(`Total layanan aktif: ${formatInteger(totalServices)} layanan`);
  }
  if (totalCategories > 0) {
    messages.push(`Kategori tersedia: ${formatInteger(totalCategories)} kategori`);
  }

  if (state.topServices.length > 0) {
    const topService = state.topServices[0];
    const shortName = String(topService.service_name || '')
      .replace(/\s+/g, ' ')
      .trim()
      .slice(0, 120);
    messages.push(`Layanan terpopuler saat ini: #${topService.service_id} ${shortName}`);
  }

  messages.push('Gunakan data target yang valid agar order diproses lebih cepat.');
  messages.push('Setelah bayar QRIS, lakukan konfirmasi agar admin bisa memproses order otomatis.');

  panelInfoTickerTextEl.textContent = messages.join(' | ');

  if (servicesSyncMetaEl) {
    const syncText = meta.synced_at
      ? `Sinkron terakhir: ${formatDateTime(meta.synced_at)}`
      : 'Sinkronisasi data layanan aktif';
    servicesSyncMetaEl.textContent = syncText;
  }
}

function renderDashboardHighlights() {
  if (!dashboardHighlightsEl) return;

  const rows = Array.isArray(state.panelHighlights) ? state.panelHighlights : [];
  if (!rows.length) {
    dashboardHighlightsEl.innerHTML = '<div class="box" style="margin:0;">Belum ada update layanan terbaru saat ini.</div>';
    return;
  }

  dashboardHighlightsEl.innerHTML = rows.map((service) => {
    const title = String(service.name || service.title || '-');
    const rowId = String(service.id ?? '-');
    const hasServiceMetrics = Number(service.sell_price || 0) > 0 || Number(service.min || 0) > 0 || Number(service.max || 0) > 0;

    const metaText = hasServiceMetrics
      ? `${String(service.category || 'Lainnya')} | Harga/K ${rupiah(service.sell_price || 0)} | Min ${formatInteger(service.min || 0)} - Max ${formatInteger(service.max || 0)}`
      : (String(service.summary || service.category || 'Update layanan terbaru') || 'Update layanan terbaru');

    return `
      <article class="service-highlight">
        <div class="service-highlight-title">#${escapeHtml(rowId)} - ${escapeHtml(title)}</div>
        <div class="service-highlight-meta">${escapeHtml(metaText)}</div>
      </article>
    `;
  }).join('');
}

async function fetchSession(options = {}) {
  const attempts = Math.max(1, Number(options.attempts || 1));
  const softFail = options.softFail !== undefined
    ? !!options.softFail
    : !!state.user;
  const retryDelayMs = Math.max(120, Number(options.retryDelayMs || SESSION_FETCH_RETRY_DELAY_MS));

  let lastData = null;
  for (let attempt = 1; attempt <= attempts; attempt += 1) {
    const needsBypass = attempt > 1;
    const endpoint = needsBypass
      ? `./api/auth_me.php?_t=${Date.now()}_${attempt}`
      : './api/auth_me.php';

    const { data } = await apiRequest(endpoint, {
      forceRefresh: needsBypass,
      timeoutMs: needsBypass ? Math.max(API_REQUEST_TIMEOUT_MS, 70000) : API_REQUEST_TIMEOUT_MS,
    });
    lastData = data;

    if (data?.status) {
      state.user = data.data.user;
      state.stats = data.data.stats || { total_orders: 0, total_spent: 0 };
      updateAdminMenuLabel();
      startAdminPendingPoller();
      startPendingPaymentSyncPoller();
      startCsChatPoller();
      startCsChatAdminPoller();
      updateHeaderStats();
      updateAccountMenu();
      updateProfilePanel();
      setViewLoggedIn(true);
      ensureCsChatBootstrap({ force: false, silent: true }).catch(() => {
        // Ignore CS bootstrap failure here; widget can retry on open.
      });
      loadAdminCsChats({ force: false, silent: true }).catch(() => {
        // Ignore CS admin list bootstrap failure here.
      });
      return true;
    }

    if (attempt < attempts && isTransientSessionFailure(data)) {
      await waitMs(retryDelayMs * attempt);
      continue;
    }
    break;
  }

  if (softFail && state.user && isTransientSessionFailure(lastData)) {
    updateHeaderStats();
    updateAccountMenu();
    updateProfilePanel();
    return true;
  }

  stopAdminPendingPoller();
  stopPendingPaymentSyncPoller();
  stopCsChatPoller();
  stopCsChatAdminPoller();
  state.user = null;
  state.stats = { total_orders: 0, total_spent: 0 };
  state.adminPaymentOrders = [];
  state.adminPaymentOrdersLoaded = false;
  state.adminOrderHistory = [];
  state.adminOrderHistoryLoaded = false;
  state.adminOrderHistoryRequestId = 0;
  state.adminOrderHistoryFilter = {
    status: 'all',
    query: '',
    page: 1,
    perPage: 25,
    total: 0,
    totalPages: 1,
  };
  resetCsChatState();
  state.adminCsChats = [];
  state.adminCsChatsLoaded = false;
  updateHeaderStats();
  updateAccountMenu();
  updateProfilePanel();
  renderAdminOrderHistory();
  renderCsChat();
  renderAdminCsChats();
  updateAdminMenuLabel();
  setViewLoggedIn(false);
  return false;
}

async function openProfileView(options = {}) {
  const focusSettings = !!options.focusSettings;
  const nextView = 'profile';

  applyPanelView(nextView);
  updateUrlForView(nextView);
  if (state.user) {
    await ensureViewData(nextView, { force: false });
  }

  if (focusSettings && profilePasswordFormEl) {
    scrollElementIntoView(profilePasswordFormEl, 'start');
    if (currentPasswordEl) {
      setTimeout(() => {
        try {
          currentPasswordEl.focus({ preventScroll: true });
        } catch {
          currentPasswordEl.focus();
        }
      }, 180);
    }
  }
}

function selectedService() {
  const byServiceId = state.serviceIndex?.byServiceId instanceof Map
    ? state.serviceIndex.byServiceId
    : new Map();
  const byOptionLabel = state.serviceIndex?.byOptionLabel instanceof Map
    ? state.serviceIndex.byOptionLabel
    : new Map();
  const byExactName = state.serviceIndex?.byExactName instanceof Map
    ? state.serviceIndex.byExactName
    : new Map();
  const globalSortedByPrice = Array.isArray(state.serviceIndex?.globalSortedByPrice)
    ? state.serviceIndex.globalSortedByPrice
    : [];

  if (Number.isFinite(state.selectedServiceId) && state.selectedServiceId > 0) {
    const selected = byServiceId.get(state.selectedServiceId);
    if (selected) {
      return selected;
    }
  }

  if (!serviceInputEl) return null;
  const inputValue = String(serviceInputEl.value || '').trim();
  if (!inputValue) return null;

  const byIdMatch = inputValue.match(/^#?\s*(\d+)/);
  if (byIdMatch) {
    const serviceId = Number(byIdMatch[1] || 0);
    if (serviceId > 0 && byServiceId.has(serviceId)) {
      state.selectedServiceId = serviceId;
      return byServiceId.get(serviceId) || null;
    }
  }

  const normalizedInput = normalizeQuery(inputValue);
  if (!normalizedInput) {
    state.selectedServiceId = 0;
    return null;
  }

  if (byOptionLabel.has(normalizedInput)) {
    const selected = byOptionLabel.get(normalizedInput) || null;
    state.selectedServiceId = Number(selected?.id || 0);
    return selected;
  }

  if (byExactName.has(normalizedInput)) {
    const selected = byExactName.get(normalizedInput) || null;
    state.selectedServiceId = Number(selected?.id || 0);
    return selected;
  }

  if (globalSortedByPrice.length > 0) {
    const startsWithMatch = globalSortedByPrice.find((service) => String(service?.__nameNorm || '').startsWith(normalizedInput));
    if (startsWithMatch) {
      state.selectedServiceId = Number(startsWithMatch?.id || 0);
      return startsWithMatch;
    }

    const containsMatch = globalSortedByPrice.find((service) => String(service?.__nameNorm || '').includes(normalizedInput));
    if (containsMatch) {
      state.selectedServiceId = Number(containsMatch?.id || 0);
      return containsMatch;
    }
  }

  state.selectedServiceId = 0;
  return null;
}

function serviceOptionLabel(service) {
  if (!service) return '';
  return `#${service.id} - ${service.name || ''}`.trim();
}

function getSelectedCategoryName() {
  if (!categoryInputEl) return '';

  const raw = String(categoryInputEl.value || '').trim();
  if (!raw) return '';

  const categories = Array.isArray(state.serviceDirectory?.categories) ? state.serviceDirectory.categories : [];
  const normalizedRaw = normalizeQuery(raw);
  const exact = categories.find((category) => normalizeQuery(category) === normalizedRaw);
  if (exact) return exact;

  const idMatch = raw.match(/^#?\s*(\d+)/);
  if (!idMatch) return '';

  const inputId = Number(idMatch[1] || 0);
  if (!Number.isFinite(inputId) || inputId <= 0) return '';

  const byProviderCategoryId = state.serviceDirectory?.byProviderCategoryId instanceof Map
    ? state.serviceDirectory.byProviderCategoryId
    : new Map();

  if (byProviderCategoryId.has(inputId)) {
    return byProviderCategoryId.get(inputId) || '';
  }

  return '';
}

function renderTop5Services() {
  if (!top5ListEl) {
    return;
  }

  state.hasTop5Data = state.topServices.length > 0;

  if (!state.hasTop5Data) {
    top5ListEl.innerHTML = '';
    if (emergencyServiceTextEl) {
      emergencyServiceTextEl.textContent = 'Top layanan akan muncul setelah ada order sukses.';
    }
    renderPanelInfoTicker();
    syncTop5Visibility();
    return;
  }

  top5ListEl.innerHTML = state.topServices.map((service, index) => `
    <div class="top-item">
      <span class="top-title">#${index + 1} - ${escapeHtml(service.service_name || '-')}</span>
      <span class="top-meta">${escapeHtml(service.category || 'Lainnya')} | Total pembelian sukses: ${formatInteger(service.total_orders || 0)}x</span>
    </div>
  `).join('');

  if (emergencyServiceTextEl) {
    const emergency = state.topServices[0];
    if (emergency) {
      emergencyServiceTextEl.textContent = `#${emergency.service_id} - ${emergency.service_name} | ${formatInteger(emergency.total_orders || 0)} order sukses`;
    } else {
      emergencyServiceTextEl.textContent = 'Top layanan akan muncul setelah ada order sukses.';
    }
  }

  renderPanelInfoTicker();
  syncTop5Visibility();
}

function updateCommentVisibility(service) {
  if (!service) {
    commentGroupEl.classList.add('hidden');
    mentionGroupEl.classList.add('hidden');
    advancedFieldsEl.classList.add('hidden');
    komenEl.required = false;
    usernamesEl.required = false;
    if (quantityGroupEl) {
      quantityGroupEl.classList.remove('hidden');
    }
    if (quantityLabelEl) {
      quantityLabelEl.textContent = 'Jumlah (Quantity)';
    }
    if (quantityEl) {
      quantityEl.readOnly = false;
    }
    return;
  }

  const commentRequired = isCommentService(service);
  const mentionRequired = isMentionsCustomListService(service);
  const autoQuantityService = commentRequired || mentionRequired;

  if (commentRequired) {
    commentGroupEl.classList.remove('hidden');
    // Validation tetap dilakukan di JS backend/frontend agar bisa fallback ke field comments.
    komenEl.required = false;
    commentHintEl.textContent = 'Layanan ini bertipe Komen. 1 baris komentar = 1 item. Contoh 150 item = 150 baris.';
  } else {
    commentGroupEl.classList.add('hidden');
    komenEl.required = false;
    komenEl.value = '';
  }

  if (mentionRequired) {
    mentionGroupEl.classList.remove('hidden');
    // Validation tetap dilakukan di JS backend/frontend agar bisa fallback ke field komen.
    usernamesEl.required = false;
    mentionHintEl.textContent = 'Layanan ini bertipe Mentions Custom List. 1 baris username = 1 item.';
  } else {
    mentionGroupEl.classList.add('hidden');
    usernamesEl.required = false;
    usernamesEl.value = '';
  }

  const showAdvanced = commentRequired || mentionRequired;
  advancedFieldsEl.classList.toggle('hidden', !showAdvanced);

  if (quantityGroupEl) {
    quantityGroupEl.classList.toggle('hidden', autoQuantityService);
  }
  if (quantityLabelEl) {
    quantityLabelEl.textContent = autoQuantityService
      ? 'Jumlah (Otomatis dari Baris)'
      : 'Jumlah (Quantity)';
  }
  if (quantityEl) {
    quantityEl.readOnly = autoQuantityService;
  }
}

function renderServicesCatalog() {
  if (!servicesCatalogBodyEl || !servicesCatalogSummaryEl) return;

  const sortBy = String(state.serviceCatalog.sortBy || 'category_name');
  const sortDir = String(state.serviceCatalog.sortDir || 'asc') === 'desc' ? 'desc' : 'asc';

  if (serviceCatalogSortByEl && serviceCatalogSortByEl.value !== sortBy) {
    serviceCatalogSortByEl.value = sortBy;
  }

  if (serviceCatalogSortDirEl && serviceCatalogSortDirEl.value !== sortDir) {
    serviceCatalogSortDirEl.value = sortDir;
  }

  const rows = Array.isArray(state.serviceCatalogRows) ? state.serviceCatalogRows : [];
  const total = Number(state.serviceCatalogTotal || 0);
  const totalPages = Math.max(1, Number(state.serviceCatalogTotalPages || 1));
  const currentPage = Math.max(1, Number(state.serviceCatalog.page || 1));

  servicesCatalogSummaryEl.textContent = `Menampilkan ${rows.length} dari ${total} layanan (halaman ${currentPage}/${totalPages}).`;

  if (!rows.length) {
    servicesCatalogBodyEl.innerHTML = '<tr><td colspan="6">Tidak ada layanan sesuai filter.</td></tr>';
    if (servicesCatalogPaginationEl) {
      servicesCatalogPaginationEl.innerHTML = '';
    }
    return;
  }

  servicesCatalogBodyEl.innerHTML = rows.map((service) => `
    <tr>
      <td>#${escapeHtml(service.id)}</td>
      <td>${escapeHtml(service.name || '-')}</td>
      <td>${escapeHtml(service.category || '-')}</td>
      <td>${rupiah(service.sell_price || 0)}</td>
      <td>${formatInteger(service.min || 0)}</td>
      <td>${formatInteger(service.max || 0)}</td>
    </tr>
  `).join('');

  if (servicesCatalogPaginationEl) {
    if (totalPages <= 1) {
      servicesCatalogPaginationEl.innerHTML = '';
    } else {
      const current = currentPage;
      const candidates = [1, current - 2, current - 1, current, current + 1, current + 2, totalPages]
        .filter((page) => page >= 1 && page <= totalPages);
      const uniquePages = [...new Set(candidates)].sort((a, b) => a - b);
      const parts = [];

      parts.push(`<button class="page-btn" data-services-page="${Math.max(1, current - 1)}" ${current <= 1 ? 'disabled' : ''}>Sebelumnya</button>`);

      let previous = 0;
      uniquePages.forEach((page) => {
        if (previous && page - previous > 1) {
          parts.push('<span class="muted">...</span>');
        }

        parts.push(`<button class="page-btn ${page === current ? 'active' : ''}" data-services-page="${page}">${page}</button>`);
        previous = page;
      });

      parts.push(`<button class="page-btn" data-services-page="${Math.min(totalPages, current + 1)}" ${current >= totalPages ? 'disabled' : ''}>Selanjutnya</button>`);

      servicesCatalogPaginationEl.innerHTML = parts.join('');
    }
  }
}

function populateServiceCatalogCategoryOptions() {
  if (!serviceCatalogCategoryEl) return;

  const categories = Array.isArray(state.serviceDirectory?.categories) ? state.serviceDirectory.categories : [];
  const html = [
    '<option value="">Semua Kategori</option>',
    ...categories.map((category) => `<option value="${escapeHtml(category)}">${escapeHtml(category)}</option>`),
  ].join('');
  const signature = `${categories.length}:${categories[0] || ''}:${categories[categories.length - 1] || ''}`;

  if (serviceCatalogCategoryEl.dataset.signature !== signature) {
    serviceCatalogCategoryEl.innerHTML = html;
    serviceCatalogCategoryEl.dataset.signature = signature;
  }

  const selected = String(state.serviceCatalog.category || '');
  if (selected && categories.includes(selected)) {
    serviceCatalogCategoryEl.value = selected;
  } else {
    serviceCatalogCategoryEl.value = '';
    state.serviceCatalog.category = '';
  }
}

function hideCheckoutPanel() {
  state.lastCheckout = null;
  if (checkoutPanelEl) checkoutPanelEl.classList.add('hidden');
  if (checkoutSummaryEl) checkoutSummaryEl.textContent = '';
  if (checkoutMethodsEl) checkoutMethodsEl.innerHTML = '';
  if (paymentMethodSelectEl) paymentMethodSelectEl.innerHTML = '';
  if (paymentReferenceEl) paymentReferenceEl.value = '';
  if (paymentPayerNameEl) paymentPayerNameEl.value = '';
  if (paymentPayerNoteEl) paymentPayerNoteEl.value = '';
  if (paymentConfirmNoticeEl) hideNotice(paymentConfirmNoticeEl);
}

function renderCheckoutPanel(orderData) {
  if (!checkoutPanelEl || !checkoutSummaryEl) return;

  const orderType = String(orderData?.order_type || orderData?.type || 'ssm').toLowerCase() === 'game' ? 'game' : 'ssm';
  const gateway = orderData?.payment_gateway && typeof orderData.payment_gateway === 'object'
    ? orderData.payment_gateway
    : null;
  const isPakasirGateway = String(gateway?.provider || '').toLowerCase() === 'pakasir';
  const methods = Array.isArray(orderData?.payment_methods) && orderData.payment_methods.length
    ? orderData.payment_methods
    : state.paymentMethods;
  state.paymentMethods = methods;
  state.lastCheckout = {
    order_type: orderType,
    order_id: Number(orderData?.order_id || 0),
    payment_deadline_at: orderData?.payment_deadline_at || '',
    total_sell_price: Number(orderData?.total_sell_price || 0),
    payment_gateway: gateway,
  };

  const serviceName = String(orderData?.service?.name || orderData?.service_name || '-');
  const targetValue = String(orderData?.target || orderData?.data || '-');
  const quantityValue = Number(orderData?.quantity || 0);

  const displayTotal = Number(gateway?.total_payment || orderData?.total_sell_price || 0);
  const gatewayFee = Number(gateway?.fee || 0);

  const summaryLines = [
    `Order ID: #${orderData?.order_id || '-'}`,
    `Jenis: ${orderType === 'game' ? 'Topup Game' : 'SSM Panel'}`,
    `Layanan: ${serviceName}`,
    `Target: ${targetValue}`,
    `Jumlah: ${formatInteger(quantityValue)}`,
    `Total Pembayaran: ${rupiah(displayTotal)}`,
    `Batas Pembayaran: ${formatDateTime(gateway?.expired_at || orderData?.payment_deadline_at || '')}`,
  ];
  if (isPakasirGateway) {
    summaryLines.push(`Gateway: Pakasir (${String(gateway?.method || 'QRIS')})`);
    if (gatewayFee > 0) {
      summaryLines.push(`Biaya Gateway: ${rupiah(gatewayFee)}`);
    }
  }
  checkoutSummaryEl.textContent = summaryLines.join('\n');

  if (checkoutMethodsEl) {
    const gatewayCard = isPakasirGateway ? `
      <div class="contact-link">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5H3V5Zm0 8h18v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-6Zm4 2v2h6v-2H7Z"></path></svg>
        <div>
          <strong>Pakasir QRIS</strong>
          <span>${escapeHtml(gateway?.pay_url || 'Lanjutkan pembayaran dari QR code pada popup.')}</span>
        </div>
      </div>
    ` : '';

    checkoutMethodsEl.innerHTML = gatewayCard + methods.map((method) => `
      <div class="contact-link">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 5H5a2 2 0 0 0-2 2v13l4-3h12a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2Zm-7 9H7v-2h5v2Zm5-4H7V8h10v2Z"></path></svg>
        <div>
          <strong>${escapeHtml(method.name)}</strong>
          <span>${escapeHtml(method.account_number)} a.n. ${escapeHtml(method.account_name || '-')}</span>
        </div>
      </div>
    `).join('');
  }

  if (paymentMethodSelectEl) {
    paymentMethodSelectEl.innerHTML = methods.map((method) => (
      `<option value="${escapeHtml(method.code)}">${escapeHtml(method.name)} - ${escapeHtml(method.account_number)}</option>`
    )).join('');
  }

  checkoutPanelEl.classList.remove('hidden');
}

function renderAdminPaymentOrders() {
  if (!adminPaymentSectionEl || !adminPaymentBodyEl) return;

  const isAdmin = String(state.user?.role || '') === 'admin';
  adminPaymentSectionEl.classList.toggle('hidden', !isAdmin);
  if (!isAdmin) return;

  if (!state.adminPaymentOrders.length) {
    adminPaymentBodyEl.innerHTML = '<tr><td colspan="8">Tidak ada order menunggu pembayaran.</td></tr>';
    return;
  }

  adminPaymentBodyEl.innerHTML = state.adminPaymentOrders.map((order) => `
    <tr>
      <td>#${escapeHtml(order.id)}</td>
      <td>${escapeHtml(order.username || '-')}</td>
      <td>${escapeHtml(order.service_name || '-')}</td>
      <td>${rupiah(order.total_sell_price || 0)}</td>
      <td>${escapeHtml(order.payment_channel_name || order.payment_method || '-')}</td>
      <td>${escapeHtml(order.payment_confirmed_at ? formatDateTime(order.payment_confirmed_at) : 'Belum konfirmasi')}</td>
      <td>${escapeHtml(formatDateTime(order.payment_deadline_at || ''))}</td>
      <td>
        <button type="button" class="mini-btn success" data-admin-pay-action="verify" data-admin-pay-order="${escapeHtml(order.id)}">Verifikasi</button>
        <button type="button" class="mini-btn danger" data-admin-pay-action="cancel" data-admin-pay-order="${escapeHtml(order.id)}">Batalkan</button>
      </td>
    </tr>
  `).join('');
}

function normalizeAdminOrderHistoryStatus(value) {
  const allowed = new Set(['all', 'waiting', 'processing', 'success', 'failed']);
  const key = String(value || '').trim().toLowerCase();
  if (allowed.has(key)) {
    return key;
  }

  return 'all';
}

function adminOrderHistoryStatusLabel(status) {
  const normalized = normalizeAdminOrderHistoryStatus(status);
  switch (normalized) {
    case 'waiting':
      return 'Menunggu Pembayaran';
    case 'processing':
      return 'Diproses';
    case 'success':
      return 'Selesai';
    case 'failed':
      return 'Dibatalkan / Gagal';
    default:
      return 'Semua Status';
  }
}

function renderAdminOrderHistoryPagination() {
  if (!adminOrderHistoryPaginationEl) return;

  const totalPages = Math.max(1, Number(state.adminOrderHistoryFilter?.totalPages || 1));
  const current = Math.max(1, Number(state.adminOrderHistoryFilter?.page || 1));
  if (totalPages <= 1) {
    adminOrderHistoryPaginationEl.innerHTML = '';
    return;
  }

  const candidates = [1, current - 2, current - 1, current, current + 1, current + 2, totalPages]
    .filter((page) => page >= 1 && page <= totalPages);
  const uniquePages = [...new Set(candidates)].sort((a, b) => a - b);
  const parts = [];

  parts.push(`<button class="page-btn" data-admin-history-page="${Math.max(1, current - 1)}" ${current <= 1 ? 'disabled' : ''}>Sebelumnya</button>`);

  let previous = 0;
  uniquePages.forEach((page) => {
    if (previous && page - previous > 1) {
      parts.push('<span class="muted">...</span>');
    }

    parts.push(`<button class="page-btn ${page === current ? 'active' : ''}" data-admin-history-page="${page}">${page}</button>`);
    previous = page;
  });

  parts.push(`<button class="page-btn" data-admin-history-page="${Math.min(totalPages, current + 1)}" ${current >= totalPages ? 'disabled' : ''}>Selanjutnya</button>`);
  adminOrderHistoryPaginationEl.innerHTML = parts.join('');
}

function renderAdminOrderHistory() {
  if (!adminOrderHistorySectionEl || !adminOrderHistoryBodyEl) return;

  const isAdmin = String(state.user?.role || '') === 'admin';
  adminOrderHistorySectionEl.classList.toggle('hidden', !isAdmin);
  if (!isAdmin) return;

  const filter = state.adminOrderHistoryFilter || {};
  const page = Math.max(1, Number(filter.page || 1));
  const perPage = Math.max(10, Number(filter.perPage || 25));
  const total = Math.max(0, Number(filter.total || 0));
  const totalPages = Math.max(1, Number(filter.totalPages || 1));
  const rows = Array.isArray(state.adminOrderHistory) ? state.adminOrderHistory : [];

  if (adminOrderHistoryStatusEl) {
    adminOrderHistoryStatusEl.value = normalizeAdminOrderHistoryStatus(filter.status);
  }
  if (adminOrderHistoryPerPageEl) {
    adminOrderHistoryPerPageEl.value = String(perPage);
  }
  if (adminOrderHistorySearchEl && adminOrderHistorySearchEl.value !== String(filter.query || '')) {
    adminOrderHistorySearchEl.value = String(filter.query || '');
  }

  if (adminOrderHistorySummaryEl) {
    const hasRows = rows.length > 0;
    const start = hasRows ? ((page - 1) * perPage) + 1 : 0;
    const end = hasRows ? (start + rows.length - 1) : 0;
    adminOrderHistorySummaryEl.textContent = hasRows
      ? `Menampilkan ${rows.length} data (baris ${start}-${end} dari ${total}) | Filter: ${adminOrderHistoryStatusLabel(filter.status)}`
      : `Belum ada data riwayat untuk filter "${adminOrderHistoryStatusLabel(filter.status)}".`;
  }

  if (!rows.length) {
    adminOrderHistoryBodyEl.innerHTML = '<tr><td colspan="11">Belum ada data riwayat pembelian.</td></tr>';
    renderAdminOrderHistoryPagination();
    return;
  }

  adminOrderHistoryBodyEl.innerHTML = rows.map((order) => {
    const normalizedStatus = normalizeOrderStatus(order);
    const statusLabel = displayOrderStatus(normalizedStatus);
    const targetRaw = String(order.target || '-');
    const targetShort = targetRaw.length > 120 ? `${targetRaw.slice(0, 120)}...` : targetRaw;
    const paymentBuyerAt = order.payment_confirmed_at ? formatDateTime(order.payment_confirmed_at) : '';
    const paymentAdminAt = order.payment_confirmed_by_admin_at ? formatDateTime(order.payment_confirmed_by_admin_at) : '';
    const paymentLabel = paymentBuyerAt
      ? `Buyer: ${paymentBuyerAt}${paymentAdminAt ? ` | Admin: ${paymentAdminAt}` : ''}`
      : 'Belum konfirmasi';
    const noteRaw = String(order.error_message || '').trim();
    const note = noteRaw !== '' ? noteRaw : '-';
    const createdAt = formatDateTime(order.created_at || order.updated_at || '');

    return `
      <tr>
        <td>#${escapeHtml(order.id)}</td>
        <td>${escapeHtml(order.username || '-')}</td>
        <td>${escapeHtml(order.service_name || '-')}</td>
        <td title="${escapeHtml(targetRaw)}">${escapeHtml(targetShort)}</td>
        <td>${formatInteger(order.quantity || 0)}</td>
        <td>${rupiah(order.total_sell_price || 0)}</td>
        <td><span class="status ${statusClass(normalizedStatus)}">${escapeHtml(statusLabel)}</span></td>
        <td>${escapeHtml(paymentLabel)}</td>
        <td>${escapeHtml(order.provider_order_id || '-')}</td>
        <td>${escapeHtml(createdAt)}</td>
        <td title="${escapeHtml(note)}">${escapeHtml(note)}</td>
      </tr>
    `;
  }).join('');

  renderAdminOrderHistoryPagination();
}

function clearTicketDetail() {
  state.ticketDetail = null;
  state.ticketMessages = [];
  if (ticketDetailPanelEl) {
    ticketDetailPanelEl.classList.add('hidden');
  }
  if (ticketDetailTitleEl) {
    ticketDetailTitleEl.textContent = 'Detail Tiket';
  }
  if (ticketDetailMetaEl) {
    ticketDetailMetaEl.textContent = 'Pilih tiket untuk melihat percakapan.';
  }
  if (ticketMessagesEl) {
    ticketMessagesEl.innerHTML = '';
  }
  if (ticketReplyMessageEl) {
    ticketReplyMessageEl.value = '';
    ticketReplyMessageEl.disabled = true;
  }
  if (ticketReplyBtnEl) {
    ticketReplyBtnEl.disabled = true;
  }
  if (ticketCloseBtnEl) {
    ticketCloseBtnEl.classList.add('hidden');
  }
  if (ticketReopenBtnEl) {
    ticketReopenBtnEl.classList.add('hidden');
  }
  hideNotice(ticketDetailNoticeEl);
}

function renderTicketDetail() {
  const ticket = state.ticketDetail || null;
  const messages = Array.isArray(state.ticketMessages) ? state.ticketMessages : [];

  if (!ticket || !ticketDetailPanelEl) {
    clearTicketDetail();
    return;
  }

  const isAdmin = String(state.user?.role || '') === 'admin';
  const isOwner = Number(ticket.user_id || 0) === Number(state.user?.id || 0);
  const canManage = isAdmin || isOwner;
  const isClosed = String(ticket.status || '').toLowerCase() === 'closed';

  if (ticketDetailTitleEl) {
    ticketDetailTitleEl.textContent = `Tiket #${ticket.id} - ${ticket.subject || '-'}`;
  }
  if (ticketDetailMetaEl) {
    const owner = ticket.username ? `@${ticket.username}` : '-';
    const orderInfo = ticket.order_id ? `Order #${ticket.order_id}` : 'Tanpa order';
    ticketDetailMetaEl.textContent = `${owner} | ${orderInfo} | Status ${ticketStatusLabel(ticket.status)} | Update ${formatDateTime(ticket.updated_at || ticket.last_message_at || ticket.created_at || '')}`;
  }

  if (ticketMessagesEl) {
    if (!messages.length) {
      ticketMessagesEl.innerHTML = '<div class="box" style="margin:0;">Belum ada pesan pada tiket ini.</div>';
    } else {
      ticketMessagesEl.innerHTML = messages.map((item) => {
        const role = String(item.sender_role || 'user');
        const cssClass = role === 'admin' ? 'ticket-message admin' : 'ticket-message';
        const senderName = item.username ? `@${item.username}` : (role === 'admin' ? 'Admin' : 'User');
        const roleText = role === 'admin' ? 'Admin' : 'Buyer';
        return `
          <article class="${cssClass}">
            <div class="ticket-message-header">
              <strong>${escapeHtml(senderName)}</strong>
              <span>${escapeHtml(roleText)} | ${escapeHtml(formatDateTime(item.created_at || ''))}</span>
            </div>
            <div class="ticket-message-body">${escapeHtml(item.message || '-')}</div>
          </article>
        `;
      }).join('');
    }
  }

  if (ticketReplyMessageEl) {
    ticketReplyMessageEl.disabled = isClosed && !isAdmin;
  }
  if (ticketReplyBtnEl) {
    ticketReplyBtnEl.disabled = isClosed && !isAdmin;
  }
  if (ticketCloseBtnEl) {
    ticketCloseBtnEl.classList.toggle('hidden', !canManage || isClosed);
  }
  if (ticketReopenBtnEl) {
    ticketReopenBtnEl.classList.toggle('hidden', !canManage || !isClosed);
  }

  ticketDetailPanelEl.classList.remove('hidden');
}

function renderTickets() {
  if (!ticketBodyEl) return;

  const statusFilter = String(state.ticketFilter.status || 'all').toLowerCase();
  const query = normalizeQuery(state.ticketFilter.query);
  const isAdmin = String(state.user?.role || '') === 'admin';
  const rawTickets = Array.isArray(state.tickets) ? state.tickets : [];

  const rows = rawTickets.filter((ticket) => {
    const status = String(ticket.status || '').toLowerCase();
    if (statusFilter !== 'all' && status !== statusFilter) {
      return false;
    }

    if (!query) {
      return true;
    }

    const searchable = [
      ticket.id,
      ticket.subject,
      ticket.username,
      ticket.order_id,
      ticket.category,
      ticket.last_message,
    ].join(' ').toLowerCase();

    return searchable.includes(query);
  });

  if (!rows.length) {
    ticketBodyEl.innerHTML = '<tr><td colspan="7">Belum ada tiket.</td></tr>';
    return;
  }

  ticketBodyEl.innerHTML = rows.map((ticket) => `
    <tr>
      <td>#${escapeHtml(ticket.id)}</td>
      <td>
        <strong>${escapeHtml(ticket.subject || '-')}</strong>
        <div class="muted">${escapeHtml(ticket.category || '-')}${isAdmin && ticket.username ? ` | @${escapeHtml(ticket.username)}` : ''}</div>
      </td>
      <td>${ticket.order_id ? `#${escapeHtml(ticket.order_id)}` : '-'}</td>
      <td><span class="status ${ticketStatusClass(ticket.status)}">${escapeHtml(ticketStatusLabel(ticket.status))}</span></td>
      <td>${escapeHtml(ticketPriorityLabel(ticket.priority))}</td>
      <td>${escapeHtml(formatDateTime(ticket.last_message_at || ticket.updated_at || ticket.created_at || ''))}</td>
      <td><button type="button" class="mini-btn ghost" data-open-ticket="${escapeHtml(ticket.id)}">Buka</button></td>
    </tr>
  `).join('');
}

async function loadTickets(options = {}) {
  if (!ticketBodyEl) return;
  const force = !!options.force;

  if (!force && state.ticketsLoaded) {
    renderTickets();
    return;
  }

  const params = new URLSearchParams({
    limit: '200',
    status: String(state.ticketFilter.status || 'all'),
    q: String(state.ticketFilter.query || ''),
  });
  const requestId = ++state.ticketRequestId;
  const endpoint = `./api/tickets.php?${params.toString()}${force ? `&_t=${Date.now()}` : ''}`;
  const { data } = await apiRequest(endpoint);

  if (requestId !== state.ticketRequestId) {
    return;
  }

  if (!data?.status) {
    state.tickets = [];
    state.ticketsLoaded = false;
    renderTickets();
    showNotice(ticketNoticeEl, 'err', data?.data?.msg || 'Gagal memuat tiket.');
    return;
  }

  state.tickets = Array.isArray(data.data?.tickets) ? data.data.tickets : [];
  state.ticketsLoaded = true;
  hideNotice(ticketNoticeEl);
  renderTickets();
}

async function loadTicketDetail(ticketId) {
  const id = Number(ticketId || 0);
  if (!Number.isFinite(id) || id <= 0) return;

  showNotice(ticketDetailNoticeEl, 'info', `Memuat detail tiket #${id}...`);
  const { data } = await apiRequest(`./api/ticket_detail.php?id=${id}`);
  if (!data?.status) {
    showNotice(ticketDetailNoticeEl, 'err', data?.data?.msg || 'Gagal memuat detail tiket.');
    return;
  }

  state.ticketDetail = data.data?.ticket || null;
  state.ticketMessages = Array.isArray(data.data?.messages) ? data.data.messages : [];
  hideNotice(ticketDetailNoticeEl);
  renderTicketDetail();
}

async function createTicket() {
  if (!ticketFormEl) return;

  const payload = {
    subject: String(ticketSubjectEl?.value || '').trim(),
    category: String(ticketCategoryEl?.value || '').trim(),
    order_id: String(ticketOrderIdEl?.value || '').trim(),
    priority: String(ticketPriorityEl?.value || 'normal').trim(),
    message: String(ticketMessageEl?.value || '').trim(),
  };

  if (!payload.subject || !payload.message) {
    showNotice(ticketNoticeEl, 'err', 'Subjek dan pesan tiket wajib diisi.');
    return;
  }

  showNotice(ticketNoticeEl, 'info', 'Membuat tiket...');
  const { data } = await apiRequest('./api/ticket_create.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });

  if (!data?.status) {
    showNotice(ticketNoticeEl, 'err', data?.data?.msg || 'Gagal membuat tiket.');
    return;
  }

  showNotice(ticketNoticeEl, 'ok', data?.data?.msg || 'Tiket berhasil dibuat.');
  if (ticketSubjectEl) ticketSubjectEl.value = '';
  if (ticketOrderIdEl) ticketOrderIdEl.value = '';
  if (ticketMessageEl) ticketMessageEl.value = '';
  if (ticketPriorityEl) ticketPriorityEl.value = 'normal';
  if (ticketCategoryEl) ticketCategoryEl.value = 'Laporan Order';

  await loadTickets({ force: true });
  const createdId = Number(data?.data?.ticket?.id || 0);
  if (createdId > 0) {
    await loadTicketDetail(createdId);
  }
}

async function replyTicket() {
  const ticketId = Number(state.ticketDetail?.id || 0);
  if (ticketId <= 0) {
    showNotice(ticketDetailNoticeEl, 'err', 'Pilih tiket terlebih dahulu.');
    return;
  }

  const message = String(ticketReplyMessageEl?.value || '').trim();
  if (!message) {
    showNotice(ticketDetailNoticeEl, 'err', 'Pesan balasan tidak boleh kosong.');
    return;
  }

  showNotice(ticketDetailNoticeEl, 'info', 'Mengirim balasan tiket...');
  const { data } = await apiRequest('./api/ticket_reply.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      id: ticketId,
      message,
    }),
  });

  if (!data?.status) {
    showNotice(ticketDetailNoticeEl, 'err', data?.data?.msg || 'Gagal mengirim balasan tiket.');
    return;
  }

  if (ticketReplyMessageEl) {
    ticketReplyMessageEl.value = '';
  }
  showNotice(ticketDetailNoticeEl, 'ok', data?.data?.msg || 'Balasan berhasil dikirim.');
  await Promise.all([
    loadTickets({ force: true }),
    loadTicketDetail(ticketId),
  ]);
  if (String(state.user?.role || '') === 'admin') {
    await loadAdminCsChats({ force: true, silent: true });
  }
}

async function updateTicketStatus(action) {
  const ticketId = Number(state.ticketDetail?.id || 0);
  if (ticketId <= 0) {
    showNotice(ticketDetailNoticeEl, 'err', 'Pilih tiket terlebih dahulu.');
    return;
  }

  const normalizedAction = String(action || '').toLowerCase();
  if (!['close', 'reopen'].includes(normalizedAction)) {
    return;
  }

  showNotice(ticketDetailNoticeEl, 'info', normalizedAction === 'close' ? 'Menutup tiket...' : 'Membuka kembali tiket...');
  const { data } = await apiRequest('./api/ticket_update.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      id: ticketId,
      action: normalizedAction,
    }),
  });

  if (!data?.status) {
    showNotice(ticketDetailNoticeEl, 'err', data?.data?.msg || 'Gagal memperbarui tiket.');
    return;
  }

  showNotice(ticketDetailNoticeEl, 'ok', data?.data?.msg || 'Status tiket diperbarui.');
  await Promise.all([
    loadTickets({ force: true }),
    loadTicketDetail(ticketId),
  ]);
}

function csChatModeLabel(mode) {
  const normalized = String(mode || '').toLowerCase();
  if (normalized === 'admin') return 'Admin aktif';
  if (normalized === 'closed') return 'Chat ditutup';
  return 'Bot aktif';
}

function isCsChatPanelOpen() {
  return !!csChatPanelEl && !csChatPanelEl.classList.contains('hidden');
}

function updateCsChatUnreadBadge() {
  if (!csChatUnreadEl) return;
  const count = Math.max(0, Number(state.csChat?.unreadCount || 0));
  csChatUnreadEl.textContent = count > 99 ? '99+' : String(count);
  csChatUnreadEl.classList.toggle('hidden', count <= 0);
}

function updateCsChatWidgetVisibility() {
  const loggedIn = !!state.user;
  if (csChatWidgetEl) {
    csChatWidgetEl.classList.toggle('hidden', !loggedIn);
  }
  if (!loggedIn) {
    setCsChatPanelOpen(false);
  }
}

function setCsChatPanelOpen(open) {
  const canOpen = !!open && !!state.user;
  if (csChatPanelEl) {
    csChatPanelEl.classList.toggle('hidden', !canOpen);
  }
  if (csChatToggleEl) {
    csChatToggleEl.setAttribute('aria-expanded', canOpen ? 'true' : 'false');
  }
  if (canOpen) {
    state.csChat.unreadCount = 0;
    updateCsChatUnreadBadge();
    hideNotice(csChatNoticeEl);
    if (csChatInputEl) {
      setTimeout(() => {
        try {
          csChatInputEl.focus({ preventScroll: true });
        } catch {
          csChatInputEl.focus();
        }
      }, 80);
    }
  }
}

function resetCsChatState() {
  state.csChat = {
    ticketId: 0,
    mode: 'bot',
    status: 'open',
    messages: [],
    lastMessageId: 0,
    loaded: false,
    unreadCount: 0,
  };
  updateCsChatUnreadBadge();
}

function renderCsChatMessages() {
  if (!csChatMessagesEl) return;
  const rows = Array.isArray(state.csChat?.messages) ? state.csChat.messages : [];
  if (!rows.length) {
    csChatMessagesEl.innerHTML = '<div class="box" style="margin:0;">Mulai percakapan dengan customer service.</div>';
    return;
  }

  csChatMessagesEl.innerHTML = rows.map((item) => {
    const senderRole = String(item.sender_role || 'user').toLowerCase();
    const rawMessage = String(item.message || '').trim();
    const isBot = senderRole === 'admin' && rawMessage.startsWith('[BOT]');
    const message = isBot ? rawMessage.replace(/^\[BOT\]\s*/i, '') : rawMessage;
    const isSelf = senderRole === 'user' && Number(item.user_id || 0) === Number(state.user?.id || 0);
    const cssClass = [
      'cs-chat-msg',
      isSelf ? 'self' : '',
      isBot ? 'bot' : '',
    ].filter(Boolean).join(' ');
    const senderLabel = isSelf
      ? 'Kamu'
      : (isBot ? 'Bot CS' : (senderRole === 'admin' ? 'Admin' : (item.username ? `@${item.username}` : 'Buyer')));

    return `
      <article class="${cssClass}">
        <div class="cs-chat-meta">
          <strong>${escapeHtml(senderLabel)}</strong>
          <span>${escapeHtml(formatDateTime(item.created_at || ''))}</span>
        </div>
        <div class="cs-chat-body">${escapeHtml(message || '-')}</div>
      </article>
    `;
  }).join('');

  if (isCsChatPanelOpen()) {
    csChatMessagesEl.scrollTop = csChatMessagesEl.scrollHeight;
  }
}

function renderCsChat() {
  if (csChatModeTextEl) {
    csChatModeTextEl.textContent = csChatModeLabel(state.csChat?.mode || 'bot');
  }

  const canTakeover = String(state.user?.role || '') === 'admin'
    && Number(state.csChat?.ticketId || 0) > 0
    && String(state.csChat?.mode || '').toLowerCase() !== 'admin';
  if (csChatTakeoverBtnEl) {
    csChatTakeoverBtnEl.classList.toggle('hidden', !canTakeover);
  }

  renderCsChatMessages();
  updateCsChatUnreadBadge();
  updateCsChatWidgetVisibility();
}

async function ensureCsChatBootstrap(options = {}) {
  if (!state.user) return false;
  const force = !!options.force;
  const silent = !!options.silent;

  if (!force && state.csChat.loaded && Number(state.csChat.ticketId || 0) > 0) {
    renderCsChat();
    return true;
  }

  if (csChatRequestInFlight) {
    return false;
  }

  csChatRequestInFlight = true;
  if (!silent && isCsChatPanelOpen()) {
    showNotice(csChatNoticeEl, 'info', 'Memuat chat customer service...');
  }

  try {
    const endpoint = force
      ? `./api/cs_chat_bootstrap.php?_t=${Date.now()}`
      : './api/cs_chat_bootstrap.php';
    const { data } = await apiRequest(endpoint, {
      forceRefresh: force,
      cacheTtlMs: 1000,
    });

    if (!data?.status) {
      if (!silent) {
        showNotice(csChatNoticeEl, 'err', data?.data?.msg || 'Gagal memuat chat customer service.');
      }
      return false;
    }

    const payload = data.data || {};
    const ticket = payload.ticket || {};
    const messages = Array.isArray(payload.messages) ? payload.messages : [];

    state.csChat.ticketId = Number(ticket.id || 0);
    state.csChat.mode = String(payload.mode || 'bot').toLowerCase();
    state.csChat.status = String(ticket.status || 'open').toLowerCase();
    state.csChat.messages = messages;
    state.csChat.lastMessageId = Math.max(
      0,
      Number(payload.last_message_id || 0),
      ...messages.map((item) => Number(item?.id || 0))
    );
    state.csChat.loaded = true;
    state.csChat.unreadCount = 0;

    hideNotice(csChatNoticeEl);
    renderCsChat();
    return true;
  } catch (error) {
    if (!silent) {
      showNotice(csChatNoticeEl, 'err', error.message || 'Gagal memuat chat customer service.');
    }
    return false;
  } finally {
    csChatRequestInFlight = false;
  }
}

function mergeCsChatMessages(incomingMessages) {
  const incoming = Array.isArray(incomingMessages) ? incomingMessages : [];
  if (!incoming.length) {
    return;
  }

  const byId = new Map();
  const merged = [];

  const appendRow = (row) => {
    const rowId = Number(row?.id || 0);
    if (rowId <= 0) return;
    if (byId.has(rowId)) return;
    byId.set(rowId, true);
    merged.push(row);
  };

  (Array.isArray(state.csChat.messages) ? state.csChat.messages : []).forEach(appendRow);
  incoming.forEach(appendRow);
  merged.sort((a, b) => Number(a.id || 0) - Number(b.id || 0));

  state.csChat.messages = merged;
  state.csChat.lastMessageId = Math.max(
    Number(state.csChat.lastMessageId || 0),
    ...incoming.map((row) => Number(row?.id || 0))
  );
}

async function pollCsChat(options = {}) {
  if (!state.user) return;
  if (csChatRequestInFlight) return;

  const force = !!options.force;
  if (!state.csChat.loaded || Number(state.csChat.ticketId || 0) <= 0) {
    const ready = await ensureCsChatBootstrap({ force: false, silent: true });
    if (!ready) return;
  }

  const ticketId = Number(state.csChat.ticketId || 0);
  if (ticketId <= 0) return;

  const afterId = force ? 0 : Math.max(0, Number(state.csChat.lastMessageId || 0));
  csChatRequestInFlight = true;

  try {
    const params = new URLSearchParams({
      ticket_id: String(ticketId),
      after_id: String(afterId),
    });
    if (force) {
      params.set('_t', String(Date.now()));
    }

    const { data } = await apiRequest(`./api/cs_chat_poll.php?${params.toString()}`, {
      forceRefresh: force,
      cacheTtlMs: 800,
    });

    if (!data?.status) {
      return;
    }

    const payload = data.data || {};
    const incomingMessages = Array.isArray(payload.messages) ? payload.messages : [];
    const wasOpen = isCsChatPanelOpen();

    if (afterId <= 0) {
      state.csChat.messages = incomingMessages;
      state.csChat.lastMessageId = Math.max(
        0,
        Number(payload.last_message_id || 0),
        ...incomingMessages.map((row) => Number(row?.id || 0))
      );
    } else {
      mergeCsChatMessages(incomingMessages);
    }

    state.csChat.mode = String(payload.mode || state.csChat.mode || 'bot').toLowerCase();
    state.csChat.ticketId = Number(payload.ticket_id || state.csChat.ticketId || 0);
    state.csChat.loaded = true;

    if (!wasOpen && incomingMessages.length > 0) {
      const incomingForUser = incomingMessages.filter((row) => String(row?.sender_role || '').toLowerCase() === 'admin').length;
      state.csChat.unreadCount = Math.max(0, Number(state.csChat.unreadCount || 0) + incomingForUser);
    }

    renderCsChat();
  } finally {
    csChatRequestInFlight = false;
  }
}

function stopCsChatPoller() {
  if (csChatPollTimer) {
    clearInterval(csChatPollTimer);
    csChatPollTimer = null;
  }
}

function startCsChatPoller() {
  stopCsChatPoller();
  if (!state.user) return;
  csChatPollTimer = setInterval(() => {
    if (document.hidden) return;
    pollCsChat({ force: false }).catch(() => {
      // Ignore background polling errors for CS chat.
    });
  }, CS_CHAT_POLL_MS);
}

function renderAdminCsChats() {
  if (!adminCsSectionEl || !adminCsBodyEl) return;
  const isAdmin = String(state.user?.role || '') === 'admin';
  adminCsSectionEl.classList.toggle('hidden', !isAdmin);
  if (!isAdmin) {
    return;
  }

  const rows = Array.isArray(state.adminCsChats) ? state.adminCsChats : [];
  if (!rows.length) {
    adminCsBodyEl.innerHTML = '<tr><td colspan="7">Belum ada chat customer service.</td></tr>';
    return;
  }

  adminCsBodyEl.innerHTML = rows.map((chat) => {
    const chatId = Number(chat.id || 0);
    const mode = String(chat.mode || (String(chat.category || '').toLowerCase().includes('admin') ? 'admin' : 'bot')).toLowerCase();
    const modeLabel = mode === 'admin' ? 'Admin' : 'Bot';
    const status = String(chat.status || 'open').toLowerCase();
    const isClosed = status === 'closed';
    const canTakeover = mode !== 'admin' && !isClosed;
    const lastMessage = String(chat.last_message || '-');
    const shortLastMessage = lastMessage.length > 120 ? `${lastMessage.slice(0, 120)}...` : lastMessage;

    return `
      <tr>
        <td>#${escapeHtml(chatId)}</td>
        <td>@${escapeHtml(chat.username || '-')}</td>
        <td><span class="status ${mode === 'admin' ? 's-completed' : 's-other'}">${escapeHtml(modeLabel)}</span></td>
        <td><span class="status ${ticketStatusClass(status)}">${escapeHtml(ticketStatusLabel(status))}</span></td>
        <td title="${escapeHtml(lastMessage)}">${escapeHtml(shortLastMessage)}</td>
        <td>${escapeHtml(formatDateTime(chat.last_message_at || chat.updated_at || ''))}</td>
        <td>
          <button type="button" class="mini-btn ghost" data-admin-cs-open="${escapeHtml(chatId)}">Buka</button>
          ${canTakeover ? `<button type="button" class="mini-btn success" data-admin-cs-takeover="${escapeHtml(chatId)}">Ambil Alih</button>` : ''}
        </td>
      </tr>
    `;
  }).join('');
}

async function loadAdminCsChats(options = {}) {
  const isAdmin = String(state.user?.role || '') === 'admin';
  if (!isAdmin) {
    state.adminCsChats = [];
    state.adminCsChatsLoaded = false;
    renderAdminCsChats();
    return;
  }

  const force = !!options.force;
  const silent = !!options.silent;
  if (!force && state.adminCsChatsLoaded) {
    renderAdminCsChats();
    return;
  }
  if (csChatAdminRequestInFlight) return;

  csChatAdminRequestInFlight = true;
  try {
    const endpoint = force
      ? `./api/cs_chat_admin_list.php?status=active&limit=120&_t=${Date.now()}`
      : './api/cs_chat_admin_list.php?status=active&limit=120';
    const { data } = await apiRequest(endpoint, {
      forceRefresh: force,
      cacheTtlMs: 1500,
    });

    if (!data?.status) {
      state.adminCsChats = [];
      state.adminCsChatsLoaded = false;
      renderAdminCsChats();
      if (!silent) {
        showNotice(adminCsNoticeEl, 'err', data?.data?.msg || 'Gagal memuat chat customer service.');
      }
      return;
    }

    state.adminCsChats = Array.isArray(data.data?.chats) ? data.data.chats : [];
    state.adminCsChatsLoaded = true;
    hideNotice(adminCsNoticeEl);
    renderAdminCsChats();
  } finally {
    csChatAdminRequestInFlight = false;
  }
}

function stopCsChatAdminPoller() {
  if (csChatAdminPollTimer) {
    clearInterval(csChatAdminPollTimer);
    csChatAdminPollTimer = null;
  }
}

function startCsChatAdminPoller() {
  stopCsChatAdminPoller();
  const isAdmin = String(state.user?.role || '') === 'admin';
  if (!isAdmin) return;
  csChatAdminPollTimer = setInterval(() => {
    if (document.hidden) return;
    loadAdminCsChats({ force: true, silent: true }).catch(() => {
      // Ignore CS admin polling errors.
    });
  }, CS_CHAT_ADMIN_POLL_MS);
}

async function sendCsChatMessage() {
  if (!state.user) return;
  const message = String(csChatInputEl?.value || '').trim();
  if (message === '') {
    showNotice(csChatNoticeEl, 'err', 'Pesan tidak boleh kosong.');
    return;
  }

  if (csChatSendBtnEl) csChatSendBtnEl.disabled = true;
  showNotice(csChatNoticeEl, 'info', 'Mengirim pesan...');

  try {
    const { data } = await apiRequest('./api/cs_chat_send.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        ticket_id: Number(state.csChat.ticketId || 0),
        message,
      }),
    });

    if (!data?.status) {
      showNotice(csChatNoticeEl, 'err', data?.data?.msg || 'Gagal mengirim pesan.');
      return;
    }

    const payload = data.data || {};
    const messages = Array.isArray(payload.messages) ? payload.messages : [];
    state.csChat.ticketId = Number(payload.ticket_id || state.csChat.ticketId || 0);
    state.csChat.mode = String(payload.mode || state.csChat.mode || 'bot').toLowerCase();
    state.csChat.messages = messages;
    state.csChat.lastMessageId = Math.max(
      0,
      Number(payload.last_message_id || 0),
      ...messages.map((row) => Number(row?.id || 0))
    );
    state.csChat.loaded = true;
    state.csChat.unreadCount = 0;
    if (csChatInputEl) {
      csChatInputEl.value = '';
    }

    hideNotice(csChatNoticeEl);
    renderCsChat();
    if (String(state.user?.role || '') === 'admin') {
      await loadAdminCsChats({ force: true, silent: true });
    }
  } finally {
    if (csChatSendBtnEl) csChatSendBtnEl.disabled = false;
  }
}

async function adminTakeoverCsChat(ticketId) {
  const id = Number(ticketId || 0);
  if (id <= 0) return;
  const adminNote = window.prompt('Pesan takeover admin (opsional):', '');
  if (adminNote === null) return;

  showNotice(adminCsNoticeEl, 'info', `Mengambil alih chat #${id}...`);
  const { data } = await apiRequest('./api/cs_chat_admin_takeover.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      ticket_id: id,
      message: adminNote,
    }),
  });

  if (!data?.status) {
    showNotice(adminCsNoticeEl, 'err', data?.data?.msg || 'Gagal mengambil alih chat.');
    return;
  }

  showNotice(adminCsNoticeEl, 'ok', data?.data?.msg || 'Chat berhasil diambil alih admin.');
  await Promise.all([
    loadAdminCsChats({ force: true, silent: true }),
    loadTickets({ force: true }),
  ]);

  if (Number(state.ticketDetail?.id || 0) === id) {
    await loadTicketDetail(id);
  }

  if (Number(state.csChat.ticketId || 0) === id) {
    await pollCsChat({ force: true }).catch(() => {
      // Ignore CS widget refresh errors after takeover.
    });
  }
}

async function openAdminCsTicket(ticketId) {
  const id = Number(ticketId || 0);
  if (id <= 0) return;
  applyPanelView('ticket');
  updateUrlForView('ticket');
  await ensureViewData('ticket', { force: true });
  await loadTicketDetail(id);
}

async function saveAccountSettings() {
  if (!profilePasswordFormEl) return;

  const currentPassword = String(currentPasswordEl?.value || '');
  const newPassword = String(newPasswordEl?.value || '');
  const confirmPassword = String(confirmPasswordEl?.value || '');

  if (!currentPassword || !newPassword || !confirmPassword) {
    showNotice(profilePasswordNoticeEl, 'err', 'Semua field password wajib diisi.');
    return;
  }

  if (newPassword.length < 6) {
    showNotice(profilePasswordNoticeEl, 'err', 'Password baru minimal 6 karakter.');
    return;
  }

  if (newPassword !== confirmPassword) {
    showNotice(profilePasswordNoticeEl, 'err', 'Konfirmasi password baru tidak sama.');
    return;
  }

  showNotice(profilePasswordNoticeEl, 'info', 'Menyimpan pengaturan akun...');
  const { data } = await apiRequest('./api/auth_change_password.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      current_password: currentPassword,
      new_password: newPassword,
      confirm_password: confirmPassword,
    }),
  });

  if (!data?.status) {
    showNotice(profilePasswordNoticeEl, 'err', data?.data?.msg || 'Gagal menyimpan pengaturan akun.');
    return;
  }

  if (currentPasswordEl) currentPasswordEl.value = '';
  if (newPasswordEl) newPasswordEl.value = '';
  if (confirmPasswordEl) confirmPasswordEl.value = '';
  showNotice(profilePasswordNoticeEl, 'ok', data?.data?.msg || 'Pengaturan akun berhasil diperbarui.');
}

function fillCategoryOptions() {
  if (!categoryInputEl || !categoryOptionsEl) return;

  const rawInput = String(categoryInputEl.value || '');
  const query = normalizeQuery(rawInput);
  const idQuery = rawInput.replace(/\D+/g, '');
  const idQueryNum = Number(idQuery || 0);
  const categoryEntries = Array.isArray(state.serviceDirectory?.categoryEntries)
    ? state.serviceDirectory.categoryEntries
    : [];
  const byProviderCategoryId = state.serviceDirectory?.byProviderCategoryId instanceof Map
    ? state.serviceDirectory.byProviderCategoryId
    : new Map();

  let priorityCategory = '';
  if (idQueryNum > 0) {
    priorityCategory = String(byProviderCategoryId.get(idQueryNum) || '');
  }

  const exactMatches = [];
  const startsWithMatches = [];
  const includesMatches = [];
  const others = [];

  categoryEntries.forEach((entry) => {
    const name = String(entry?.name || '');
    if (!name) return;

    const searchable = String(entry?.searchable || '');
    let rank = 3;

    if (priorityCategory && name === priorityCategory) {
      rank = 0;
    } else if (query) {
      if (searchable.startsWith(query)) {
        rank = 1;
      } else if (searchable.includes(query)) {
        rank = 2;
      }
    } else if (idQuery && entry?.providerIdText) {
      const providerIdText = String(entry.providerIdText);
      if (providerIdText === idQuery) {
        rank = 1;
      } else if (providerIdText.startsWith(idQuery)) {
        rank = 2;
      }
    }

    if (rank === 0) {
      exactMatches.push(name);
    } else if (rank === 1) {
      startsWithMatches.push(name);
    } else if (rank === 2) {
      includesMatches.push(name);
    } else {
      others.push(name);
    }
  });

  const ordered = [...exactMatches, ...startsWithMatches, ...includesMatches, ...others];
  const seen = new Set();
  const limitedCategories = ordered.filter((name) => {
    if (seen.has(name)) return false;
    seen.add(name);
    return true;
  }).slice(0, CATEGORY_OPTIONS_LIMIT);

  categoryOptionsEl.innerHTML = limitedCategories
    .map((category) => `<option value="${escapeHtml(String(category))}"></option>`)
    .join('');
  renderCategorySuggestions(limitedCategories, rawInput);

  const resolvedCategory = getSelectedCategoryName();
  if (resolvedCategory !== state.lastResolvedCategory) {
    state.lastResolvedCategory = resolvedCategory;
    fillServiceOptions({ force: true }).catch(() => {
      // Ignore background category-triggered option refresh errors.
    });
  } else {
    scheduleServiceInfoUpdate();
  }
}

async function fillServiceOptions(options = {}) {
  if (!serviceInputEl || !serviceOptionsEl) return;
  const force = !!options.force;
  const serviceLimit = SERVICE_OPTIONS_LIMIT;

  const category = getSelectedCategoryName();
  const currentService = selectedService();
  const currentServiceMatchesCategory = !currentService
    || !category
    || (currentService.category || 'Lainnya') === category;

  if (!currentServiceMatchesCategory) {
    serviceInputEl.value = '';
    state.selectedServiceId = 0;
  }

  const rawServiceInput = String(serviceInputEl.value || '').trim();
  const serviceIdMatch = rawServiceInput.match(/^#?\s*(\d+)/);
  const isIdQuery = !!serviceIdMatch;
  const serviceQuery = serviceIdMatch
    ? String(serviceIdMatch[1] || '').trim()
    : normalizeQuery(rawServiceInput);

  if (!isIdQuery && serviceQuery && serviceQuery.length < SERVICE_QUERY_MIN_CHARS && !force) {
    const infoText = IS_IOS_DEVICE
      ? `Ketik minimal ${SERVICE_QUERY_MIN_CHARS} karakter nama layanan untuk iPhone/iPad.`
      : 'Ketik nama atau ID layanan untuk menampilkan hasil.';
    if (serviceInfoEl) {
      serviceInfoEl.textContent = infoText;
    }
    renderServiceSuggestions([], rawServiceInput);
    return;
  }

  const cacheKey = `${category || '__all__'}::${serviceQuery}`;
  const baseCacheKey = `${category || '__all__'}::`;
  const renderKey = `${cacheKey}::${rawServiceInput}`;
  const hasCachedForKey = state.servicesSearchCache.has(cacheKey);
  const previousSearch = state.serviceSearchLast && typeof state.serviceSearchLast === 'object'
    ? state.serviceSearchLast
    : { category: '', query: '', isIdQuery: false, results: [] };

  if (!force && state.serviceOptionsRenderKey === renderKey && hasCachedForKey) {
    scheduleServiceInfoUpdate();
    return;
  }

  let nextServices = (!force && state.servicesSearchCache.has(cacheKey))
    ? (state.servicesSearchCache.get(cacheKey) || [])
    : null;

  const canRefineFromPrevious = !force
    && !Array.isArray(nextServices)
    && !!serviceQuery
    && previousSearch.category === (category || '__all__')
    && !previousSearch.isIdQuery
    && serviceQuery.startsWith(String(previousSearch.query || ''))
    && Array.isArray(previousSearch.results)
    && previousSearch.results.length > 0
    && previousSearch.results.length < serviceLimit;

  if (canRefineFromPrevious) {
    const refined = sortServicesByRankAndPrice(previousSearch.results, serviceQuery, isIdQuery ? serviceQuery : '')
      .filter((service) => serviceLocalRank(service, serviceQuery, isIdQuery ? serviceQuery : '') < 99)
      .slice(0, serviceLimit);
    nextServices = refined;
    state.servicesSearchCache.set(cacheKey, refined);
  }

  if (!Array.isArray(nextServices)) {
    const requestId = ++state.servicesSearchRequestId;
    const params = new URLSearchParams({
      mode: 'search',
      variant: DEFAULT_SERVICE_VARIANT,
      q: serviceQuery,
      limit: String(serviceLimit),
    });
    if (category) {
      params.set('category', category);
    }
    const { data } = await apiRequest(`./api/services.php?${params.toString()}`, {
      timeoutMs: IS_IOS_DEVICE ? 22000 : 18000,
      cacheTtlMs: 60000,
    });
    if (requestId !== state.servicesSearchRequestId) {
      return;
    }

    if (!data?.status) {
      state.services = [];
      buildServiceIndex();
      serviceOptionsEl.innerHTML = '';
      state.selectedServiceId = 0;
      if (serviceInfoEl) {
        serviceInfoEl.textContent = data?.data?.msg || 'Daftar layanan sedang tidak tersedia.';
      }
      state.serviceOptionsRenderKey = '';
      renderServiceSuggestions([], rawServiceInput);
      scheduleServiceInfoUpdate();
      return;
    }

    nextServices = Array.isArray(data.data?.services) ? data.data.services : [];
    state.servicesSearchCache.set(cacheKey, nextServices);
    if (state.servicesSearchCache.size > SERVICES_SEARCH_CACHE_MAX_KEYS) {
      const firstKey = state.servicesSearchCache.keys().next();
      if (!firstKey.done) {
        state.servicesSearchCache.delete(firstKey.value);
      }
    }

    if (serviceQuery) {
      const baseServices = state.servicesSearchCache.has(baseCacheKey)
        ? (state.servicesSearchCache.get(baseCacheKey) || [])
        : [];

      if (Array.isArray(baseServices) && baseServices.length > 0) {
        const merged = [];
        const seen = new Set();
        const addUnique = (service) => {
          if (!service || typeof service !== 'object') return;
          const id = Number(service.id || 0);
          const key = id > 0
            ? `id:${id}`
            : `${String(service.name || '').toLowerCase()}|${String(service.category || '').toLowerCase()}`;
          if (seen.has(key)) return;
          seen.add(key);
          merged.push(service);
        };

        nextServices.forEach(addUnique);
        baseServices.forEach(addUnique);
        nextServices = merged.slice(0, serviceLimit);
      }
    }
  }

  state.services = [...nextServices];
  buildServiceIndex();
  const pool = Array.isArray(state.services) ? state.services : [];
  const limitedServices = pool.slice(0, serviceLimit);

  serviceOptionsEl.innerHTML = limitedServices
    .map((service) => `<option value="${escapeHtml(serviceOptionLabel(service))}"></option>`)
    .join('');
  renderServiceSuggestions(limitedServices, rawServiceInput);

  const resolvedService = selectedService();
  if (resolvedService) {
    serviceInputEl.value = serviceOptionLabel(resolvedService);
  }

  state.serviceSearchLast = {
    category: category || '__all__',
    query: serviceQuery,
    isIdQuery,
    results: [...limitedServices],
  };
  state.serviceOptionsRenderKey = renderKey;
  scheduleServiceInfoUpdate();
}

function updateServiceInfo() {
  const service = selectedService();
  updateCommentVisibility(service);

  if (!service) {
    if (pricePer1000El) {
      pricePer1000El.value = rupiah(0);
    }
    serviceInfoEl.textContent = 'Pilih layanan untuk melihat detail harga dan ketentuan.';
    return;
  }

  const autoQuantityService = isCommentService(service) || isMentionsCustomListService(service);
  const qty = selectedServiceQuantity(service);
  const sellPricePer1000 = Number(service.sell_price_per_1000 ?? service.sell_price ?? 0);
  const sellUnitPrice = Number(service.sell_unit_price ?? (sellPricePer1000 / 1000));
  const estimate = qty > 0 ? Math.ceil((sellPricePer1000 * qty) / 1000) : 0;

  if (quantityEl && autoQuantityService) {
    quantityEl.value = qty > 0 ? String(qty) : '';
  }

  if (pricePer1000El) {
    pricePer1000El.value = rupiah(sellPricePer1000);
  }

  serviceInfoEl.textContent = [
    `[${service.category || 'Lainnya'}] ${service.name}`,
    `Harga Jual / 1000: ${rupiah(sellPricePer1000)}`,
    `Harga Satuan: ${rupiahUnit(sellUnitPrice)} / item`,
    `Min/Max: ${service.min} - ${service.max}`,
    autoQuantityService
      ? `Jumlah Otomatis dari Baris: ${formatInteger(qty)} item`
      : '',
    service.speed ? `Speed: ${service.speed}` : '',
    service.provider_service_status ? `Status Layanan: ${service.provider_service_status}` : '',
    `Estimasi Total: ${rupiah(estimate)}`,
    isCommentService(service)
      ? 'Tipe layanan: Komen (komentar wajib diisi).'
      : isMentionsCustomListService(service)
        ? 'Tipe layanan: Mentions Custom List (usernames wajib diisi).'
        : 'Tipe layanan: Standar.',
    service.note ? `Catatan: ${service.note}` : '',
  ].filter(Boolean).join('\n');
}

async function loadServices(options = {}) {
  const force = !!options.force;

  if (!force && state.servicesLoaded && state.serviceDirectory.categories.length) {
    return true;
  }

  if (state.servicesLoadingPromise) {
    return state.servicesLoadingPromise;
  }

  state.servicesLoadingPromise = (async () => {
    if (serviceInfoEl) {
      serviceInfoEl.textContent = 'Memuat kategori layanan...';
    }

    const endpoint = force
      ? `./api/services.php?mode=categories&variant=${encodeURIComponent(DEFAULT_SERVICE_VARIANT)}&_t=${Date.now()}`
      : `./api/services.php?mode=categories&variant=${encodeURIComponent(DEFAULT_SERVICE_VARIANT)}`;
    const { data } = await apiRequest(endpoint, {
      timeoutMs: IS_IOS_DEVICE ? 24000 : 20000,
      cacheTtlMs: 120000,
    });

    if (!data?.status) {
      state.services = [];
      state.serviceDirectory = {
        categories: [],
        categoryEntries: [],
        byProviderCategoryId: new Map(),
      };
      state.serviceIndex = {
        categories: [],
        categoryEntries: [],
        byCategory: new Map(),
        byCategorySortedByPrice: new Map(),
        byServiceId: new Map(),
        byProviderCategoryId: new Map(),
        categoryProviderId: new Map(),
        globalSortedByPrice: [],
        byExactName: new Map(),
        byOptionLabel: new Map(),
      };
      state.selectedServiceId = 0;
      state.servicesSearchCache = new Map();
      state.serviceSearchLast = {
        category: '',
        query: '',
        isIdQuery: false,
        results: [],
      };
      state.serviceOptionsRenderKey = '';
      state.lastResolvedCategory = '';
      state.serviceCatalogRows = [];
      state.serviceCatalogTotal = 0;
      state.serviceCatalogTotalPages = 1;
      state.serviceCatalogLoaded = false;
      state.panelInfoMeta = {
        total_services: 0,
        total_categories: 0,
        synced_at: '',
      };
      populateServiceCatalogCategoryOptions();
      state.servicesLoaded = false;
      updateCommentVisibility(null);
      if (pricePer1000El) {
        pricePer1000El.value = rupiah(0);
      }
      if (serviceInfoEl) {
        serviceInfoEl.textContent = data?.data?.msg || 'Daftar layanan sedang tidak tersedia.';
      }
      renderServicesCatalog();
      renderPanelInfoTicker();
      hideAllSuggestions(true);
      return false;
    }

    if (force) {
      state.servicesSearchCache = new Map();
      state.serviceSearchLast = {
        category: '',
        query: '',
        isIdQuery: false,
        results: [],
      };
      state.serviceOptionsRenderKey = '';
      state.lastResolvedCategory = '';
      state.selectedServiceId = 0;
      state.serviceCatalogLoaded = false;
    }

    const categories = Array.isArray(data.data?.categories) ? data.data.categories : [];
    const meta = (data.data && typeof data.data.meta === 'object' && data.data.meta !== null)
      ? data.data.meta
      : null;
    if (meta) {
      state.panelInfoMeta = {
        ...state.panelInfoMeta,
        total_services: Number(meta.total_services || state.panelInfoMeta.total_services || 0),
        total_categories: Number(meta.total_categories || state.panelInfoMeta.total_categories || 0),
        synced_at: String(meta.synced_at || state.panelInfoMeta.synced_at || ''),
      };
    }
    buildServiceDirectory(categories);

    state.services = [];
    buildServiceIndex();
    state.servicesLoaded = true;
    populateServiceCatalogCategoryOptions();

    if (servicesCatalogPerPageEl) {
      servicesCatalogPerPageEl.value = String(state.serviceCatalog.perPage || 50);
    }
    if (serviceCatalogSortByEl) {
      serviceCatalogSortByEl.value = String(state.serviceCatalog.sortBy || 'category_name');
    }
    if (serviceCatalogSortDirEl) {
      serviceCatalogSortDirEl.value = String(state.serviceCatalog.sortDir || 'asc');
    }

    fillCategoryOptions();
    await fillServiceOptions({ force: false });
    renderPanelInfoTicker();
    if (serviceInfoEl && !selectedService()) {
      serviceInfoEl.textContent = 'Pilih layanan (bisa langsung cari ID/nama) atau pilih kategori dulu agar lebih spesifik.';
    }
    return true;
  })();

  try {
    return await state.servicesLoadingPromise;
  } finally {
    state.servicesLoadingPromise = null;
  }
}

async function loadServicesCatalog(options = {}) {
  if (!servicesCatalogBodyEl) return;

  const force = !!options.force;
  if (!state.servicesLoaded) {
    const ok = await loadServices({ force });
    if (!ok) {
      return;
    }
  }

  const requestId = ++state.serviceCatalogRequestId;
  if (servicesCatalogSummaryEl) {
    servicesCatalogSummaryEl.textContent = 'Memuat daftar layanan...';
  }

  const params = new URLSearchParams({
    mode: 'catalog',
    variant: DEFAULT_SERVICE_VARIANT,
    q: String(state.serviceCatalog.query || '').trim(),
    category: String(state.serviceCatalog.category || '').trim(),
    sort_by: String(state.serviceCatalog.sortBy || 'category_name'),
    sort_dir: String(state.serviceCatalog.sortDir || 'asc') === 'desc' ? 'desc' : 'asc',
    page: String(Math.max(1, Number(state.serviceCatalog.page || 1))),
    per_page: String(Math.max(1, Number(state.serviceCatalog.perPage || 50))),
  });

  const endpoint = force
    ? `./api/services.php?${params.toString()}&_t=${Date.now()}`
    : `./api/services.php?${params.toString()}`;
  const { data } = await apiRequest(endpoint, {
    timeoutMs: IS_IOS_DEVICE ? 24000 : 20000,
    cacheTtlMs: 90000,
  });

  if (requestId !== state.serviceCatalogRequestId) {
    return;
  }

  if (!data?.status) {
    state.serviceCatalogRows = [];
    state.serviceCatalogTotal = 0;
    state.serviceCatalogTotalPages = 1;
    state.serviceCatalogLoaded = false;
    renderServicesCatalog();
    if (servicesCatalogSummaryEl) {
      servicesCatalogSummaryEl.textContent = data?.data?.msg || 'Gagal memuat daftar layanan.';
    }
    return;
  }

  const payload = data.data || {};
  state.serviceCatalogRows = Array.isArray(payload.rows) ? payload.rows : [];
  state.serviceCatalogTotal = Number(payload.total || 0);
  state.serviceCatalogTotalPages = Math.max(1, Number(payload.total_pages || 1));
  state.serviceCatalog.page = Math.max(1, Number(payload.page || state.serviceCatalog.page || 1));
  state.serviceCatalog.perPage = Math.max(1, Number(payload.per_page || state.serviceCatalog.perPage || 50));
  if (servicesCatalogPerPageEl) {
    servicesCatalogPerPageEl.value = String(state.serviceCatalog.perPage);
  }
  state.serviceCatalogLoaded = true;
  renderServicesCatalog();
}

async function loadPanelHighlights(options = {}) {
  const force = !!options.force;
  if (!force && state.panelHighlightsLoaded) {
    renderDashboardHighlights();
    renderPanelInfoTicker();
    return;
  }

  const endpoint = force
    ? `./api/services.php?mode=highlights&variant=${encodeURIComponent(HIGHLIGHT_SERVICE_VARIANT)}&limit=6&_t=${Date.now()}`
    : `./api/services.php?mode=highlights&variant=${encodeURIComponent(HIGHLIGHT_SERVICE_VARIANT)}&limit=6`;
  const { data } = await apiRequest(endpoint);
  const payload = data?.data || {};
  let highlights = Array.isArray(payload.services) ? payload.services : [];

  if ((!data?.status || highlights.length === 0)) {
    const newsEndpoint = force
      ? `./api/news_list.php?limit=6&_t=${Date.now()}`
      : './api/news_list.php?limit=6';
    const { data: newsData } = await apiRequest(newsEndpoint);
    if (newsData?.status) {
      const newsRows = Array.isArray(newsData.data?.news) ? newsData.data.news : [];
      if (newsRows.length > 0) {
        highlights = newsRows.slice(0, 6).map((news, idx) => ({
          id: String(news.id || `news-${idx + 1}`),
          name: String(news.title || 'Update Layanan'),
          category: 'Update Layanan',
          summary: String(news.summary || ''),
          sell_price: 0,
          min: 0,
          max: 0,
        }));
      }
    }
  }

  state.panelHighlights = highlights;
  state.panelHighlightsLoaded = highlights.length > 0;

  if (payload.meta && typeof payload.meta === 'object') {
    state.panelInfoMeta = {
      ...state.panelInfoMeta,
      total_services: Number(payload.meta.total_services || state.panelInfoMeta.total_services || 0),
      total_categories: Number(payload.meta.total_categories || state.panelInfoMeta.total_categories || 0),
      synced_at: String(payload.meta.synced_at || state.panelInfoMeta.synced_at || ''),
    };
  }

  renderDashboardHighlights();
  renderPanelInfoTicker();
}

async function loadTop5Services(options = {}) {
  const force = !!options.force;
  if (!force && state.topServicesLoaded) {
    renderTop5Services();
    return;
  }

  const { data } = await apiRequest('./api/top_services.php?limit=5');
  if (!data.status) {
    state.topServices = [];
    state.topServicesLoaded = false;
    renderTop5Services();
    return;
  }

  state.topServices = Array.isArray(data.data?.services) ? data.data.services : [];
  state.topServicesLoaded = true;
  renderTop5Services();
}

async function loadAdminPaymentOrders(options = {}) {
  const force = !!options.force;
  const silent = !!options.silent;
  const detectNew = !!options.detectNew;
  const isAdmin = String(state.user?.role || '') === 'admin';
  if (!isAdmin) {
    state.adminPaymentOrders = [];
    state.adminPaymentOrdersLoaded = false;
    renderAdminPaymentOrders();
    updateAdminMenuLabel();
    return;
  }

  if (!force && state.adminPaymentOrdersLoaded) {
    renderAdminPaymentOrders();
    return;
  }

  const requestId = ++state.adminPaymentRequestId;
  const { data } = await apiRequest('./api/order_admin_payments.php?status=waiting&limit=100');
  if (requestId !== state.adminPaymentRequestId) {
    return;
  }
  if (!data.status) {
    const errMsg = String(data?.data?.msg || '');
    state.adminPaymentOrders = [];
    state.adminPaymentOrdersLoaded = false;
    renderAdminPaymentOrders();
    updateAdminMenuLabel();
    if (!silent && adminPaymentNoticeEl) {
      showNotice(adminPaymentNoticeEl, 'err', errMsg || 'Gagal memuat verifikasi pembayaran.');
    }
    if (errMsg.toLowerCase().includes('login')) {
      stopAdminPendingPoller();
    }
    return;
  }

  state.adminPaymentOrders = Array.isArray(data.data?.orders) ? data.data.orders : [];
  state.adminPaymentOrdersLoaded = true;
  renderAdminPaymentOrders();
  updateAdminMenuLabel();
  if (detectNew) {
    handleAdminPendingNotifications(state.adminPaymentOrders);
  } else if (!adminPendingInitialized) {
    handleAdminPendingNotifications(state.adminPaymentOrders);
  }
}

async function loadAdminOrderHistory(options = {}) {
  const force = !!options.force;
  const silent = !!options.silent;
  const isAdmin = String(state.user?.role || '') === 'admin';
  if (!isAdmin) {
    state.adminOrderHistory = [];
    state.adminOrderHistoryLoaded = false;
    state.adminOrderHistoryRequestId = 0;
    state.adminOrderHistoryFilter = {
      ...state.adminOrderHistoryFilter,
      page: 1,
      total: 0,
      totalPages: 1,
    };
    renderAdminOrderHistory();
    return;
  }

  if (!force && state.adminOrderHistoryLoaded) {
    renderAdminOrderHistory();
    return;
  }

  const currentFilter = state.adminOrderHistoryFilter || {};
  const nextStatus = normalizeAdminOrderHistoryStatus(currentFilter.status);
  const nextQuery = String(currentFilter.query || '').trim();
  const nextPage = Math.max(1, Number(currentFilter.page || 1));
  const nextPerPage = Math.min(100, Math.max(10, Number(currentFilter.perPage || 25)));

  state.adminOrderHistoryFilter = {
    ...state.adminOrderHistoryFilter,
    status: nextStatus,
    query: nextQuery,
    page: nextPage,
    perPage: nextPerPage,
  };

  const params = new URLSearchParams({
    status: nextStatus,
    page: String(nextPage),
    per_page: String(nextPerPage),
  });
  if (nextQuery) {
    params.set('q', nextQuery);
  }

  const requestId = ++state.adminOrderHistoryRequestId;
  const { data } = await apiRequest(`./api/order_admin_history.php?${params.toString()}`);
  if (requestId !== state.adminOrderHistoryRequestId) {
    return;
  }

  if (!data.status) {
    state.adminOrderHistory = [];
    state.adminOrderHistoryLoaded = false;
    state.adminOrderHistoryFilter = {
      ...state.adminOrderHistoryFilter,
      total: 0,
      totalPages: 1,
    };
    renderAdminOrderHistory();
    if (!silent && adminOrderHistoryNoticeEl) {
      showNotice(adminOrderHistoryNoticeEl, 'err', data?.data?.msg || 'Gagal memuat riwayat pembelian admin.');
    }
    return;
  }

  const payload = (data.data && typeof data.data === 'object') ? data.data : {};
  state.adminOrderHistory = Array.isArray(payload.orders) ? payload.orders : [];
  state.adminOrderHistoryLoaded = true;
  state.adminOrderHistoryFilter = {
    ...state.adminOrderHistoryFilter,
    status: normalizeAdminOrderHistoryStatus(payload.status || nextStatus),
    query: nextQuery,
    page: Math.max(1, Number(payload.page || nextPage)),
    perPage: Math.min(100, Math.max(10, Number(payload.per_page || nextPerPage))),
    total: Math.max(0, Number(payload.total || 0)),
    totalPages: Math.max(1, Number(payload.total_pages || 1)),
  };
  renderAdminOrderHistory();
}

function normalizeOrderStatus(order) {
  const raw = `${order?.status || ''} ${order?.provider_status || ''}`.toLowerCase();

  if (raw.includes('menunggu pembayaran') || raw.includes('waiting payment')) return 'Menunggu Pembayaran';
  if (raw.includes('selesai') || raw.includes('success') || raw.includes('complete') || raw.includes('done')) return 'Selesai';
  if (raw.includes('dibatalkan') || raw.includes('error') || raw.includes('fail') || raw.includes('cancel') || raw.includes('partial')) return 'Dibatalkan';
  if (raw.includes('diproses') || raw.includes('process') || raw.includes('progress') || raw.includes('pending') || raw.includes('queue')) return 'Diproses';
  return 'Diproses';
}

function displayOrderStatus(status) {
  const normalized = String(status || '').trim();
  if (normalized === 'Menunggu Pembayaran') {
    return 'Menunggu Konfirmasi Admin';
  }

  return normalized;
}

function statusClass(status) {
  const key = String(status || '').toLowerCase();
  if (key.includes('selesai') || key.includes('success') || key.includes('complete') || key.includes('approved')) return 's-completed';
  if (key.includes('partial')) return 's-partial';
  if (key.includes('dibatalkan') || key.includes('error') || key.includes('fail') || key.includes('reject') || key.includes('cancel')) return 's-failed';
  if (key.includes('menunggu') || key.includes('diproses') || key.includes('pending') || key.includes('process') || key.includes('progress')) return 's-processing';
  return 's-other';
}

function normalizeDepositStatus(status) {
  const key = String(status || '').toLowerCase().trim();
  if (key === 'approved') return 'Approved';
  if (key === 'rejected') return 'Rejected';
  if (key === 'cancelled') return 'Cancelled';
  return 'Pending';
}

function renderHistoryTabs() {
  if (!historyStatusTabsEl) return;

  const counts = {
    'Menunggu Pembayaran': 0,
    Diproses: 0,
    Selesai: 0,
    Dibatalkan: 0,
  };

  state.orders.forEach((order) => {
    const normalized = normalizeOrderStatus(order);
    if (Object.prototype.hasOwnProperty.call(counts, normalized)) {
      counts[normalized] += 1;
    }
  });

  historyStatusTabsEl.querySelectorAll('.status-tab').forEach((button) => {
    const status = button.dataset.status || 'ALL';
    const baseLabel = button.dataset.baseLabel || button.textContent;
    button.dataset.baseLabel = baseLabel;

    const count = status === 'ALL' ? state.orders.length : (counts[status] || 0);
    const rawLabel = baseLabel.split(' (')[0];
    const displayLabel = status === 'Menunggu Pembayaran'
      ? 'Menunggu Konfirmasi Admin'
      : rawLabel;
    button.textContent = `${displayLabel} (${count})`;
    button.classList.toggle('active', status === state.history.status);
  });
}

function getFilteredOrders() {
  const idQuery = normalizeQuery(state.history.idQuery);
  const targetQuery = normalizeQuery(state.history.targetQuery);
  const serviceQuery = normalizeQuery(state.history.serviceQuery);

  return state.orders.filter((order) => {
    const normalizedStatus = normalizeOrderStatus(order);
    if (state.history.status !== 'ALL' && normalizedStatus !== state.history.status) {
      return false;
    }

    if (idQuery) {
      const idText = `${order.id || ''} ${order.provider_order_id || ''}`.toLowerCase();
      if (!idText.includes(idQuery)) return false;
    }

    if (targetQuery && !String(order.target || '').toLowerCase().includes(targetQuery)) {
      return false;
    }

    if (serviceQuery && !String(order.service_name || '').toLowerCase().includes(serviceQuery)) {
      return false;
    }

    return true;
  });
}

function renderOrdersPagination(totalPages) {
  if (!ordersPaginationEl) return;

  if (totalPages <= 1) {
    ordersPaginationEl.innerHTML = '';
    return;
  }

  const current = state.history.page;
  const candidates = [1, current - 2, current - 1, current, current + 1, current + 2, totalPages]
    .filter((page) => page >= 1 && page <= totalPages);
  const uniquePages = [...new Set(candidates)].sort((a, b) => a - b);

  const parts = [];

  parts.push(`<button class="page-btn" data-page="${Math.max(1, current - 1)}" ${current <= 1 ? 'disabled' : ''}>Sebelumnya</button>`);

  let previous = 0;
  uniquePages.forEach((page) => {
    if (previous && page - previous > 1) {
      parts.push('<span class="muted">...</span>');
    }

    parts.push(`<button class="page-btn ${page === current ? 'active' : ''}" data-page="${page}">${page}</button>`);
    previous = page;
  });

  parts.push(`<button class="page-btn" data-page="${Math.min(totalPages, current + 1)}" ${current >= totalPages ? 'disabled' : ''}>Selanjutnya</button>`);

  ordersPaginationEl.innerHTML = parts.join('');
}

function renderOrders() {
  renderHistoryTabs();

  const filtered = getFilteredOrders();
  const total = filtered.length;
  const perPage = Math.max(1, Number(state.history.perPage || 10));
  const totalPages = Math.max(1, Math.ceil(total / perPage));

  if (state.history.page > totalPages) {
    state.history.page = totalPages;
  }

  const start = (state.history.page - 1) * perPage;
  const paged = filtered.slice(start, start + perPage);

  if (historySummaryEl) {
    historySummaryEl.textContent = `Menampilkan ${paged.length} dari ${total} data`;
  }

  if (!paged.length) {
    ordersBody.innerHTML = '<tr><td colspan="9">Tidak ada data sesuai filter.</td></tr>';
    renderOrdersPagination(totalPages);
    return;
  }

  ordersBody.innerHTML = paged.map((order) => {
    const status = normalizeOrderStatus(order);
    const statusLabel = displayOrderStatus(status);
    const quantity = Number(order.quantity || 0);
    const deadline = order.payment_deadline_at ? formatDateTime(order.payment_deadline_at) : '-';

    return `
      <tr>
        <td>#${escapeHtml(order.id)}</td>
        <td>${escapeHtml(order.service_name || '-')}</td>
        <td>${escapeHtml(order.target || '-')}</td>
        <td>${formatInteger(quantity)}</td>
        <td>${rupiah(order.total_sell_price || 0)}</td>
        <td><span class="status ${statusClass(status)}">${escapeHtml(statusLabel)}</span></td>
        <td>${escapeHtml(deadline)}</td>
        <td>${escapeHtml(order.created_at || order.updated_at || '-')}</td>
        <td><button class="mini-btn ghost" data-check-order="${escapeHtml(order.id)}" type="button">Cek Status</button></td>
      </tr>
    `;
  }).join('');

  renderOrdersPagination(totalPages);
}

function normalizeRefillStatus(status) {
  const key = String(status || '').toLowerCase().trim();
  if (key.includes('selesai') || key.includes('success') || key.includes('complete')) return 'Selesai';
  if (key.includes('batal') || key.includes('cancel') || key.includes('fail') || key.includes('error') || key.includes('partial')) return 'Dibatalkan';
  return 'Diproses';
}

function renderRefills() {
  if (!refillBodyEl || !refillSummaryEl) return;

  if (!Array.isArray(state.refills) || !state.refills.length) {
    refillBodyEl.innerHTML = '<tr><td colspan="7">Belum ada data refill.</td></tr>';
    refillSummaryEl.textContent = 'Belum ada permintaan refill.';
    return;
  }

  const latest = state.refills[0];
  refillSummaryEl.textContent = `Total refill: ${formatInteger(state.refills.length)} | Terakhir #${latest.id} (${normalizeRefillStatus(latest.status)})`;

  refillBodyEl.innerHTML = state.refills.map((refill) => {
    const normalizedStatus = normalizeRefillStatus(refill.status);
    const providerRefillId = String(refill.provider_refill_id || '').trim();
    const checkBtn = providerRefillId !== ''
      ? `<button class="mini-btn ghost" data-check-refill="${escapeHtml(refill.id)}" type="button">Cek Status</button>`
      : '-';

    return `
      <tr>
        <td>#${escapeHtml(refill.id)}</td>
        <td>#${escapeHtml(refill.order_id)}</td>
        <td>${escapeHtml(refill.service_name || '-')}</td>
        <td>${escapeHtml(providerRefillId || '-')}</td>
        <td><span class="status ${statusClass(normalizedStatus)}">${escapeHtml(normalizedStatus)}</span></td>
        <td>${escapeHtml(refill.created_at || '-')}</td>
        <td>${checkBtn}</td>
      </tr>
    `;
  }).join('');
}

async function loadRefills(options = {}) {
  const force = !!options.force;
  if (!refillBodyEl) return;

  if (!force && state.refillsLoaded) {
    renderRefills();
    return;
  }

  const { data } = await apiRequest('./api/order_refills.php?limit=100');
  if (!data.status) {
    state.refills = [];
    state.refillsLoaded = false;
    renderRefills();
    if (refillStatusNoticeEl) {
      showNotice(refillStatusNoticeEl, 'err', data?.data?.msg || 'Gagal memuat data refill.');
    }
    return;
  }

  state.refills = Array.isArray(data.data?.refills) ? data.data.refills : [];
  state.refillsLoaded = true;
  renderRefills();
}

function resetNewsForm() {
  if (!newsFormEl) return;

  if (newsIdEl) newsIdEl.value = '';
  if (newsTitleEl) newsTitleEl.value = '';
  if (newsPublishedAtEl) newsPublishedAtEl.value = '';
  if (newsSourceNameEl) newsSourceNameEl.value = NEWS_SOURCE_BRAND;
  if (newsSourceUrlEl) newsSourceUrlEl.value = '';
  if (newsSummaryEl) newsSummaryEl.value = '';
  if (newsContentEl) newsContentEl.value = '';
  if (newsIsPublishedEl) newsIsPublishedEl.checked = true;
}

function openNewsModal(newsId) {
  const key = String(newsId || '').trim();
  if (!key) return;

  const news = state.news.find((item) => String(item.id || '').trim() === key);
  if (!news || !newsModalEl) return;
  const sourceName = sanitizeNewsSourceName(news.source_name);
  const sourceUrl = sanitizeNewsSourceUrl(news.source_url);

  if (newsModalTitleEl) newsModalTitleEl.textContent = news.title || 'Detail Berita';
  if (newsModalMetaEl) {
    newsModalMetaEl.textContent = `${formatDateTime(news.published_at || news.created_at)} | ${sourceName}`;
  }
  if (newsModalContentEl) {
    newsModalContentEl.textContent = news.content || news.summary || '-';
  }

  if (newsModalSourceEl) {
    if (sourceUrl) {
      newsModalSourceEl.classList.remove('hidden');
      newsModalSourceEl.href = sourceUrl;
      if (newsModalSourceNameEl) {
        newsModalSourceNameEl.textContent = sourceName;
      }
    } else {
      newsModalSourceEl.classList.add('hidden');
      newsModalSourceEl.removeAttribute('href');
    }
  }

  newsModalEl.classList.remove('hidden');
}

function closeNewsModal() {
  if (!newsModalEl) return;
  newsModalEl.classList.add('hidden');
}

function closePaymentQrModal() {
  if (!paymentQrModalEl) return;
  paymentQrModalEl.classList.add('hidden');
  if (paymentQrNoticeEl) hideNotice(paymentQrNoticeEl);
}

function defaultPaymentMethodCode() {
  const selected = String(paymentMethodSelectEl?.value || '').trim();
  if (selected) {
    return selected;
  }

  const methods = Array.isArray(state.paymentMethods) ? state.paymentMethods : [];
  const fallback = String(methods[0]?.code || 'qris').trim();
  return fallback || 'qris';
}

function isPakasirGatewayCheckout(checkout) {
  if (!checkout || typeof checkout !== 'object') return false;
  const gateway = checkout.payment_gateway;
  if (!gateway || typeof gateway !== 'object') return false;
  return String(gateway.provider || '').toLowerCase() === 'pakasir';
}

function normalizeCheckoutOrderType(value) {
  return String(value || '').toLowerCase() === 'game' ? 'game' : 'ssm';
}

function handlePostPaymentConfirmation(orderId, options = {}) {
  const normalizedOrderId = Number(orderId || 0);
  const orderType = normalizeCheckoutOrderType(options.orderType || state.lastCheckout?.order_type || 'ssm');
  const promptMessage = String(options.message || '').trim() || 'Konfirmasi pembayaran berhasil dikirim.';
  const openHistory = window.confirm(
    `${promptMessage}\n\nTekan OK untuk buka Riwayat Pesanan.\nTekan Cancel untuk lanjut cari item lain.`
  );

  closePaymentQrModal();

  if (openHistory) {
    if (orderType === 'game') {
      focusGameHistory(normalizedOrderId);
    } else {
      focusOrderHistory(normalizedOrderId);
    }
    return;
  }

  if (orderType === 'game') {
    applyPanelView('game');
    updateUrlForView('game');
    if (gameServiceInputEl) {
      try {
        gameServiceInputEl.focus({ preventScroll: false });
      } catch {
        gameServiceInputEl.focus();
      }
    }
    return;
  }

  applyPanelView('purchase');
  updateUrlForView('purchase');

  if (targetEl) {
    try {
      targetEl.focus({ preventScroll: false });
    } catch {
      targetEl.focus();
    }
  }
}

async function submitPaymentConfirmation(source = 'panel') {
  const checkout = state.lastCheckout;
  const noticeEl = source === 'modal' ? paymentQrNoticeEl : paymentConfirmNoticeEl;
  const orderType = normalizeCheckoutOrderType(checkout?.order_type || 'ssm');

  if (!checkout?.order_id) {
    showNotice(noticeEl, 'err', 'Tidak ada checkout aktif untuk dikonfirmasi.');
    return false;
  }

  const payload = {
    order_id: Number(checkout.order_id || 0),
    method_code: defaultPaymentMethodCode(),
    renotify: true,
  };

  if (!payload.method_code) {
    showNotice(noticeEl, 'err', 'Metode pembayaran tidak tersedia.');
    return false;
  }

  if (isPakasirGatewayCheckout(checkout)) {
    if (paymentConfirmBtnEl) paymentConfirmBtnEl.disabled = true;
    if (paymentQrConfirmBtnEl) paymentQrConfirmBtnEl.disabled = true;
    showNotice(noticeEl, 'info', 'Mengecek status pembayaran otomatis dari Pakasir...');

    const statusData = orderType === 'game'
      ? await requestGameOrderStatusUpdate(Number(checkout.order_id || 0))
      : await requestOrderStatusUpdate(Number(checkout.order_id || 0));

    if (!statusData?.status) {
      if (paymentConfirmBtnEl) paymentConfirmBtnEl.disabled = false;
      if (paymentQrConfirmBtnEl) paymentQrConfirmBtnEl.disabled = false;
      showNotice(noticeEl, 'err', statusData?.data?.msg || 'Gagal mengecek status pembayaran Pakasir.');
      return false;
    }

    const latestStatusRaw = String(statusData?.data?.status || '').trim();
    const latestStatus = latestStatusRaw || 'Menunggu Pembayaran';

    if (orderType === 'game') {
      await Promise.all([
        fetchSession({ attempts: SESSION_FETCH_RETRY_ATTEMPTS, softFail: true }),
        loadGameOrders({ force: true }),
        loadGameAdminPendingOrders({ force: true }),
      ]);
    } else {
      await Promise.all([
        fetchSession({ attempts: SESSION_FETCH_RETRY_ATTEMPTS, softFail: true }),
        loadOrders({ force: true }),
        loadAdminPaymentOrders({ force: true }),
        loadTop5Services({ force: true }),
      ]);
    }

    if (paymentConfirmBtnEl) paymentConfirmBtnEl.disabled = false;
    if (paymentQrConfirmBtnEl) paymentQrConfirmBtnEl.disabled = false;

    updateHeaderStats();
    const orderId = Number(checkout.order_id || 0);
    const order = orderType === 'game'
      ? (state.gameOrders.find((item) => Number(item?.id || 0) === orderId) || null)
      : (state.orders.find((item) => Number(item?.id || 0) === orderId) || null);
    const orderStatus = order
      ? (orderType === 'game' ? normalizedGameStatus(order?.status || latestStatus) : normalizeOrderStatus(order))
      : latestStatus;

    if (orderStatus === 'Diproses' || orderStatus === 'Selesai') {
      const successMessage = 'Pembayaran sudah terdeteksi otomatis. Order sedang diproses.';
      showNotice(noticeEl, 'ok', successMessage);
      if (noticeEl !== paymentConfirmNoticeEl && paymentConfirmNoticeEl) {
        showNotice(paymentConfirmNoticeEl, 'ok', successMessage);
      }
      if (noticeEl !== paymentQrNoticeEl && paymentQrNoticeEl) {
        showNotice(paymentQrNoticeEl, 'ok', successMessage);
      }
      handlePostPaymentConfirmation(orderId, { message: successMessage, orderType });
      return true;
    }

    if (orderStatus === 'Dibatalkan' || orderStatus === 'Error') {
      const failMessage = order?.error_message
        ? `Pembayaran/Order gagal: ${order.error_message}`
        : 'Order tidak dapat diproses. Cek riwayat untuk detail.';
      showNotice(noticeEl, 'err', failMessage);
      return false;
    }

    showNotice(noticeEl, 'info', 'Pembayaran belum terdeteksi. Jika sudah bayar, tunggu 10-30 detik lalu klik cek status lagi.');
    return false;
  }

  if (paymentConfirmBtnEl) paymentConfirmBtnEl.disabled = true;
  if (paymentQrConfirmBtnEl) paymentQrConfirmBtnEl.disabled = true;
  showNotice(noticeEl, 'info', 'Mengirim konfirmasi pembayaran...');

  const confirmEndpoint = orderType === 'game'
    ? './api/game_order_payment_confirm.php'
    : './api/order_payment_confirm.php';
  const { data } = await apiRequest(confirmEndpoint, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });

  if (paymentConfirmBtnEl) paymentConfirmBtnEl.disabled = false;
  if (paymentQrConfirmBtnEl) paymentQrConfirmBtnEl.disabled = false;

  if (!data.status) {
    showNotice(noticeEl, 'err', data?.data?.msg || 'Konfirmasi pembayaran gagal.');
    return false;
  }

  const successMessage = data?.data?.msg || 'Konfirmasi pembayaran berhasil.';
  showNotice(noticeEl, 'ok', successMessage);
  if (noticeEl !== paymentConfirmNoticeEl && paymentConfirmNoticeEl) {
    showNotice(paymentConfirmNoticeEl, 'ok', successMessage);
  }
  if (noticeEl !== paymentQrNoticeEl && paymentQrNoticeEl) {
    showNotice(paymentQrNoticeEl, 'ok', successMessage);
  }

  if (orderType === 'game') {
    await Promise.all([
      fetchSession({ attempts: SESSION_FETCH_RETRY_ATTEMPTS, softFail: true }),
      loadGameOrders({ force: true }),
      loadGameAdminPendingOrders({ force: true }),
    ]);
  } else {
    await Promise.all([
      fetchSession({ attempts: SESSION_FETCH_RETRY_ATTEMPTS, softFail: true }),
      loadOrders({ force: true }),
      loadAdminPaymentOrders({ force: true }),
    ]);
  }
  updateHeaderStats();
  handlePostPaymentConfirmation(payload.order_id, { orderType });
  return true;
}

function focusOrderHistory(orderId) {
  const normalizedOrderId = Number(orderId || 0);
  applyPanelView('purchase');
  updateUrlForView('purchase');

  state.history.status = 'Menunggu Pembayaran';
  state.history.page = 1;
  if (historyOrderIdSearchEl) {
    historyOrderIdSearchEl.value = normalizedOrderId > 0 ? String(normalizedOrderId) : '';
  }
  state.history.idQuery = normalizedOrderId > 0 ? String(normalizedOrderId) : '';
  renderOrders();

  if (ordersNotice) {
    showNotice(
      ordersNotice,
      'info',
      normalizedOrderId > 0
        ? `Order #${normalizedOrderId} menunggu konfirmasi admin untuk pembayaran.`
        : 'Order menunggu konfirmasi admin untuk pembayaran.'
    );
  }

  if (historySectionEl) {
    scrollElementIntoView(historySectionEl, 'start');
  }
}

function openPaymentQrModal(orderData) {
  const orderType = normalizeCheckoutOrderType(orderData?.order_type || state.lastCheckout?.order_type || 'ssm');
  if (!paymentQrModalEl || !paymentQrSummaryEl || !paymentQrImageEl) {
    if (orderType === 'game') {
      focusGameHistory(orderData?.order_id);
    } else {
      focusOrderHistory(orderData?.order_id);
    }
    return;
  }

  const orderId = Number(orderData?.order_id || 0);
  const gateway = orderData?.payment_gateway && typeof orderData.payment_gateway === 'object'
    ? orderData.payment_gateway
    : null;
  const isPakasirGateway = String(gateway?.provider || '').toLowerCase() === 'pakasir';
  const total = Number(gateway?.total_payment || orderData?.total_sell_price || 0);
  const deadline = formatDateTime(gateway?.expired_at || orderData?.payment_deadline_at || '');
  const qrisPath = resolveAssetPath(gateway?.qr_image_url || pageEl?.dataset?.qrisPath || 'assets/qris.png');
  const gatewayFee = Number(gateway?.fee || 0);

  if (paymentQrTitleEl) {
    paymentQrTitleEl.textContent = `Pembayaran Order #${orderId || '-'}`;
  }

  const summaryLines = [
    `Nominal Bayar: ${rupiah(total)}`,
    `Batas Pembayaran: ${deadline}`,
  ];
  if (isPakasirGateway) {
    summaryLines.push(`Metode: Pakasir ${String(gateway?.method || 'QRIS')}`);
    if (gatewayFee > 0) {
      summaryLines.push(`Biaya Gateway: ${rupiah(gatewayFee)}`);
    }
  }
  paymentQrSummaryEl.textContent = summaryLines.join('\n');

  paymentQrImageEl.src = qrisPath;

  if (paymentQrInstructionEl) {
    if (isPakasirGateway) {
      const lines = [
        '1. Scan QR Pakasir di atas dan bayar sesuai nominal.',
        '2. Setelah bayar, klik "Cek Status Pembayaran".',
        '3. Jika pembayaran valid, order akan diproses otomatis.',
      ];
      const payUrl = String(gateway?.pay_url || '').trim();
      if (payUrl) {
        lines.push(`Link bayar: ${payUrl}`);
      }
      paymentQrInstructionEl.textContent = lines.join('\n');
    } else {
      paymentQrInstructionEl.textContent = [
        '1. Scan QR di atas dan bayar sesuai nominal.',
        '2. Setelah transfer, klik tombol "Saya Sudah Bayar".',
        '3. Sistem akan kirim konfirmasi ke admin otomatis.',
      ].join('\n');
    }
  }

  if (paymentQrNoticeEl) hideNotice(paymentQrNoticeEl);
  if (paymentQrConfirmBtnEl) {
    paymentQrConfirmBtnEl.disabled = false;
    paymentQrConfirmBtnEl.textContent = isPakasirGateway ? 'Cek Status Pembayaran' : 'Saya Sudah Bayar';
  }
  if (paymentMethodSelectEl && !paymentMethodSelectEl.value) {
    paymentMethodSelectEl.value = defaultPaymentMethodCode();
  }
  paymentQrModalEl.classList.remove('hidden');
}

function renderNews() {
  if (!newsListEl) return;

  const webStatus = String(state.newsMeta?.web_fetch_status || '').toLowerCase();
  const webMessage = String(state.newsMeta?.web_fetch_message || '').trim();
  const showWebInfo = webStatus === 'failed' && webMessage !== '';
  const infoBanner = showWebInfo
    ? `<div class="box" style="margin:0 0 10px;">${escapeHtml(webMessage)} Menampilkan update fallback.</div>`
    : '';

  if (!state.news.length) {
    newsListEl.innerHTML = `${infoBanner}<div class="box">Belum ada berita terbaru saat ini.</div>`;
    return;
  }

  newsListEl.innerHTML = infoBanner + state.news.map((news) => `
    <article class="news-card">
      <h4>${escapeHtml(news.title || '-')}</h4>
      <div class="news-meta">${escapeHtml(formatDateTime(news.published_at || news.created_at))}</div>
      <div class="news-summary">${escapeHtml(news.summary || '-')}</div>
      <div class="news-actions">
        <button type="button" class="mini-btn ghost" data-read-news="${escapeHtml(news.id)}">Baca Selengkapnya</button>
      </div>
    </article>
  `).join('');
}

function renderAdminNews() {
  if (!newsAdminSectionEl || !newsAdminBodyEl) return;

  const isAdmin = String(state.user?.role || '') === 'admin';
  newsAdminSectionEl.classList.toggle('hidden', !isAdmin);
  if (!isAdmin) return;

  if (!state.adminNews.length) {
    newsAdminBodyEl.innerHTML = '<tr><td colspan="5">Belum ada data berita.</td></tr>';
    return;
  }

  newsAdminBodyEl.innerHTML = state.adminNews.map((news) => {
    const publishedLabel = Number(news.is_published) === 1 ? 'Published' : 'Draft';
    return `
      <tr>
        <td>#${escapeHtml(news.id)}</td>
        <td>${escapeHtml(news.title || '-')}</td>
        <td><span class="status ${statusClass(publishedLabel)}">${publishedLabel}</span></td>
        <td>${escapeHtml(formatDateTime(news.published_at || news.created_at))}</td>
        <td>
          <button type="button" class="mini-btn ghost" data-news-edit="${escapeHtml(news.id)}">Edit</button>
          <button type="button" class="mini-btn danger" data-news-delete="${escapeHtml(news.id)}">Hapus</button>
        </td>
      </tr>
    `;
  }).join('');
}

async function loadNews(options = {}) {
  const force = !!options.force;
  if (!force && state.newsLoaded) {
    renderNews();
    return;
  }

  const { data } = await apiRequest('./api/news_list.php?limit=50');
  if (!data.status) {
    state.news = [];
    state.newsLoaded = false;
    state.newsMeta = {
      web_fetch_status: '',
      web_fetch_message: '',
    };
    renderNews();
    return;
  }

  state.news = Array.isArray(data.data?.news) ? data.data.news : [];
  state.newsMeta = (data.data && typeof data.data.meta === 'object' && data.data.meta !== null)
    ? data.data.meta
    : {
      web_fetch_status: '',
      web_fetch_message: '',
    };
  state.newsLoaded = true;
  renderNews();
}

async function loadAdminNews(options = {}) {
  const force = !!options.force;
  const isAdmin = String(state.user?.role || '') === 'admin';
  if (!isAdmin) {
    state.adminNews = [];
    state.adminNewsLoaded = false;
    renderAdminNews();
    return;
  }

  if (!force && state.adminNewsLoaded) {
    renderAdminNews();
    return;
  }

  const { data } = await apiRequest('./api/news_admin_list.php?status=all&limit=100');
  if (!data.status) {
    state.adminNews = [];
    state.adminNewsLoaded = false;
    renderAdminNews();
    if (newsNoticeEl) {
      showNotice(newsNoticeEl, 'err', data?.data?.msg || 'Gagal memuat berita admin.');
    }
    return;
  }

  state.adminNews = Array.isArray(data.data?.news) ? data.data.news : [];
  state.adminNewsLoaded = true;
  renderAdminNews();
}

function fillNewsForm(news) {
  if (!news) return;
  if (newsIdEl) newsIdEl.value = String(news.id || '');
  if (newsTitleEl) newsTitleEl.value = news.title || '';
  if (newsPublishedAtEl) newsPublishedAtEl.value = toDatetimeLocalValue(news.published_at || news.created_at);
  if (newsSourceNameEl) newsSourceNameEl.value = sanitizeNewsSourceName(news.source_name);
  if (newsSourceUrlEl) newsSourceUrlEl.value = sanitizeNewsSourceUrl(news.source_url);
  if (newsSummaryEl) newsSummaryEl.value = news.summary || '';
  if (newsContentEl) newsContentEl.value = news.content || '';
  if (newsIsPublishedEl) newsIsPublishedEl.checked = Number(news.is_published) === 1;
}

function renderPaymentInfo() {
  const fallbackPath = pageEl?.dataset?.qrisPath || 'assets/qris.png';
  const fallbackReceiver = pageEl?.dataset?.qrisReceiver || 'Odyssiavault';
  const payment = state.payment || {};
  const receiverName = payment.receiver_name || fallbackReceiver;
  const minDeposit = Number(payment.min_deposit || 0);
  const maxDeposit = Number(payment.max_deposit || 0);

  if (qrisImageEl) {
    qrisImageEl.src = resolveAssetPath(payment.qris_image || fallbackPath);
  }

  if (qrisMetaEl) {
    const baseText = `Penerima: ${receiverName}`;
    if (minDeposit > 0 && maxDeposit > 0) {
      qrisMetaEl.textContent = `${baseText} | Min: ${rupiah(minDeposit)} | Max: ${rupiah(maxDeposit)}`;
    } else {
      qrisMetaEl.textContent = baseText;
    }
  }

  if (depositInstructionEl && minDeposit > 0 && maxDeposit > 0) {
    depositInstructionEl.textContent = `Scan QRIS di bawah, lalu transfer sesuai nominal final (termasuk kode unik bila ada). Batas deposit: ${rupiah(minDeposit)} - ${rupiah(maxDeposit)}.`;
  }
}

function renderDepositHistory() {
  if (!depositHistoryBodyEl) return;

  if (!state.deposits.length) {
    depositHistoryBodyEl.innerHTML = '<tr><td colspan="6">Belum ada data deposit.</td></tr>';
    return;
  }

  depositHistoryBodyEl.innerHTML = state.deposits.map((deposit) => {
    const status = normalizeDepositStatus(deposit.status);
    const amount = Number(deposit.amount || 0);
    const amountFinal = Number(deposit.amount_final || 0);
    const uniqueCode = Number(deposit.unique_code || 0);
    const transferText = uniqueCode > 0
      ? `${rupiah(amountFinal)} (kode unik: ${uniqueCode})`
      : rupiah(amountFinal);

    return `
      <tr>
        <td>#${escapeHtml(deposit.id)}</td>
        <td>${rupiah(amount)}</td>
        <td>${transferText}</td>
        <td><span class="status ${statusClass(status)}">${escapeHtml(status)}</span></td>
        <td>${escapeHtml(deposit.created_at || '-')}</td>
        <td>${escapeHtml(deposit.admin_note || '-')}</td>
      </tr>
    `;
  }).join('');
}

function renderAdminDeposits() {
  if (!depositAdminPanelEl || !depositAdminBodyEl) return;

  const isAdmin = String(state.user?.role || '') === 'admin';
  depositAdminPanelEl.classList.toggle('hidden', !isAdmin);
  if (!isAdmin) return;

  if (!state.adminDeposits.length) {
    depositAdminBodyEl.innerHTML = '<tr><td colspan="7">Tidak ada deposit pending.</td></tr>';
    return;
  }

  depositAdminBodyEl.innerHTML = state.adminDeposits.map((deposit) => {
    const status = normalizeDepositStatus(deposit.status);
    const buyerNote = [deposit.payer_name, deposit.payer_note]
      .filter(Boolean)
      .join(' | ');
    const actions = status === 'Pending'
      ? `
          <button class="mini-btn success" data-deposit-action="approve" data-deposit-id="${escapeHtml(deposit.id)}" type="button">Approve</button>
          <button class="mini-btn danger" data-deposit-action="reject" data-deposit-id="${escapeHtml(deposit.id)}" type="button">Tolak</button>
        `
      : '-';

    return `
      <tr>
        <td>#${escapeHtml(deposit.id)}</td>
        <td>${escapeHtml(deposit.username || '-')}</td>
        <td>${rupiah(deposit.amount_final || 0)}</td>
        <td><span class="status ${statusClass(status)}">${escapeHtml(status)}</span></td>
        <td>${escapeHtml(buyerNote || '-')}</td>
        <td>${escapeHtml(deposit.created_at || '-')}</td>
        <td>${actions}</td>
      </tr>
    `;
  }).join('');
}

async function loadDepositHistory(options = {}) {
  const force = !!options.force;
  if (!force && state.depositsLoaded) {
    renderPaymentInfo();
    renderDepositHistory();
    return;
  }

  const { data } = await apiRequest('./api/deposit_history.php?limit=50');

  if (!data.status) {
    state.deposits = [];
    state.depositsLoaded = false;
    renderDepositHistory();
    if (depositNoticeEl) {
      showNotice(depositNoticeEl, 'err', data?.data?.msg || 'Gagal memuat riwayat deposit.');
    }
    return;
  }

  state.payment = data.data?.payment || state.payment;
  state.deposits = Array.isArray(data.data?.deposits) ? data.data.deposits : [];
  state.depositsLoaded = true;

  renderPaymentInfo();
  renderDepositHistory();
}

async function loadAdminDeposits(options = {}) {
  const force = !!options.force;
  const isAdmin = String(state.user?.role || '') === 'admin';
  if (!isAdmin) {
    state.adminDeposits = [];
    state.adminDepositsLoaded = false;
    renderAdminDeposits();
    return;
  }

  if (!force && state.adminDepositsLoaded) {
    renderAdminDeposits();
    return;
  }

  const { data } = await apiRequest('./api/deposit_admin_list.php?status=pending&limit=100');
  if (!data.status) {
    state.adminDeposits = [];
    state.adminDepositsLoaded = false;
    renderAdminDeposits();
    if (depositAdminNoticeEl) {
      showNotice(depositAdminNoticeEl, 'err', data?.data?.msg || 'Gagal memuat data verifikasi deposit.');
    }
    return;
  }

  state.adminDeposits = Array.isArray(data.data?.deposits) ? data.data.deposits : [];
  state.adminDepositsLoaded = true;
  renderAdminDeposits();
}

async function loadOrders(options = {}) {
  const force = !!options.force;
  if (!force && state.ordersLoaded) {
    renderOrders();
    return;
  }

  const requestId = ++state.ordersRequestId;
  const { data } = await apiRequest('./api/orders.php?limit=200');
  if (requestId !== state.ordersRequestId) {
    return;
  }
  if (!data.status) {
    state.orders = [];
    state.ordersLoaded = false;
    renderOrders();
    if (ordersNotice) {
      showNotice(ordersNotice, 'err', data?.data?.msg || 'Gagal memuat riwayat order.');
    }
    return;
  }
  state.orders = data.data?.orders || [];
  state.ordersLoaded = true;
  renderOrders();
}

async function requestOrderStatusUpdate(orderId) {
  const normalizedOrderId = Number(orderId || 0);
  if (!Number.isFinite(normalizedOrderId) || normalizedOrderId <= 0) {
    return {
      status: false,
      data: { msg: 'Order ID tidak valid.' },
    };
  }

  const { data } = await apiRequest('./api/order_status.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ order_id: normalizedOrderId }),
  });

  return data || {
    status: false,
    data: { msg: 'Gagal mengecek status order.' },
  };
}

async function checkOrderStatus(orderId) {
  showNotice(ordersNotice, 'info', `Mengecek status order #${orderId}...`);
  const data = await requestOrderStatusUpdate(orderId);

  if (!data.status) {
    showNotice(ordersNotice, 'err', data?.data?.msg || 'Gagal cek status order.');
    return;
  }

  showNotice(ordersNotice, 'ok', `Status order #${orderId}: ${data.data.status}`);
  await Promise.all([loadOrders({ force: true }), loadTop5Services({ force: true })]);
}

async function requestRefill(orderId) {
  if (!refillNoticeEl) return;

  showNotice(refillNoticeEl, 'info', `Mengajukan refill untuk order #${orderId}...`);
  const { data } = await apiRequest('./api/order_refill.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ order_id: orderId }),
  });

  if (!data.status) {
    showNotice(refillNoticeEl, 'err', data?.data?.msg || 'Gagal mengajukan refill.');
    return;
  }

  const refillInfo = data.data || {};
  showNotice(
    refillNoticeEl,
    'ok',
    `Refill berhasil diajukan.\nRefill ID: #${refillInfo.refill_id || '-'}\nRefill ID Server: ${refillInfo.provider_refill_id || '-'}\nStatus: ${refillInfo.status || 'Diproses'}`
  );

  if (refillOrderIdEl) {
    refillOrderIdEl.value = '';
  }

  await loadRefills({ force: true });
}

async function checkRefillStatus(refillId) {
  if (!refillStatusNoticeEl) return;

  showNotice(refillStatusNoticeEl, 'info', `Mengecek status refill #${refillId}...`);
  const { data } = await apiRequest('./api/order_refill_status.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ refill_id: refillId }),
  });

  if (!data.status) {
    showNotice(refillStatusNoticeEl, 'err', data?.data?.msg || 'Gagal cek status refill.');
    return;
  }

  const status = data?.data?.status || 'Diproses';
  showNotice(refillStatusNoticeEl, 'ok', `Status refill #${refillId}: ${status}`);
  await loadRefills({ force: true });
}

function renderGameComingSoonState() {
  if (!GAME_TOPUP_COMING_SOON) {
    return false;
  }

  if (gameOrderFormEl) {
    const controls = gameOrderFormEl.querySelectorAll('input, select, textarea, button');
    controls.forEach((control) => {
      if (!(control instanceof HTMLInputElement || control instanceof HTMLSelectElement || control instanceof HTMLTextAreaElement || control instanceof HTMLButtonElement)) {
        return;
      }
      control.disabled = true;
    });
  }

  if (gameOrderNoticeEl) {
    showNotice(
      gameOrderNoticeEl,
      'info',
      'Fitur Topup Game sedang coming soon. Tim Odyssiavault sedang finalisasi agar lebih stabil untuk buyer.'
    );
  }

  if (gameHistorySummaryEl) {
    gameHistorySummaryEl.textContent = 'Topup Game Coming Soon';
  }

  if (gameOrdersBodyEl) {
    gameOrdersBodyEl.innerHTML = '<tr><td colspan="9">Fitur Topup Game masih coming soon.</td></tr>';
  }

  if (gameOrderNoticeBottomEl) {
    hideNotice(gameOrderNoticeBottomEl);
  }

  if (gameAdminPendingPanelEl) {
    gameAdminPendingPanelEl.classList.add('hidden');
  }

  return true;
}

function clearGameServiceSelection(options = {}) {
  const clearInput = options.clearInput !== false;
  state.gameSelectedService = null;
  gameServiceSuggestionItems = [];
  gameSuggestRenderKey = '';
  if (clearInput && gameServiceInputEl) {
    gameServiceInputEl.value = '';
  }
  if (gamePriceViewEl) {
    gamePriceViewEl.value = 'Rp0';
  }
  if (gameServiceInfoEl) {
    gameServiceInfoEl.textContent = 'Mulai ketik layanan game untuk melihat detail produk.';
  }
  hideGameServiceSuggestions();
}

function renderGameServiceInfo(service) {
  if (!gameServiceInfoEl) return;
  if (!service) {
    gameServiceInfoEl.textContent = 'Mulai ketik layanan game untuk melihat detail produk.';
    return;
  }

  const activeLabel = service.is_active ? 'Aktif' : (service.status || 'Tidak Aktif');
  const lines = [
    `Produk: ${service.name || '-'}`,
    `Kode: #${service.id || '-'}`,
    `Kategori: ${service.category || '-'}`,
    `Status: ${activeLabel}`,
    `Harga: ${rupiah(service.sell_price || 0)}`,
  ];
  gameServiceInfoEl.textContent = lines.join('\n');
}

function renderGameServiceSuggestions(services, rawInput = '') {
  if (!gameServiceSuggestPanelEl || !gameServiceInputEl) {
    return;
  }

  const list = Array.isArray(services) ? services : [];
  gameServiceSuggestionItems = list;
  const hasFocus = document.activeElement === gameServiceInputEl;
  if (!hasFocus || list.length === 0) {
    hideGameServiceSuggestions();
    return;
  }

  const query = normalizeQuery(rawInput);
  const signature = list.length > 0
    ? `${list.length}:${String(list[0]?.id || '')}:${String(list[list.length - 1]?.id || '')}`
    : '0';
  const renderKey = `${query}|${signature}`;
  if (gameSuggestRenderKey === renderKey && !gameServiceSuggestPanelEl.classList.contains('hidden')) {
    return;
  }

  const itemsHtml = list.map((service, index) => `
    <button type="button" class="suggest-option" data-suggest-kind="game-service" data-suggest-index="${index}">
      <span class="suggest-main">${escapeHtml(service.option_label || `#${service.id} - ${service.name}`)}</span>
      <span class="suggest-sub">${escapeHtml(service.category || '-')}${service.sell_price ? ` | ${escapeHtml(rupiah(service.sell_price))}` : ''}</span>
    </button>
  `).join('');

  gameServiceSuggestPanelEl.innerHTML = `
    <div class="suggest-header">Saran Layanan Game (${list.length})</div>
    <div class="suggest-list">${itemsHtml}</div>
  `;
  gameServiceSuggestPanelEl.dataset.mobile = isMobileSuggestionViewport() ? '1' : '0';
  positionSuggestionPanel(gameServiceSuggestPanelEl, gameServiceInputEl);
  gameServiceSuggestPanelEl.classList.remove('hidden');
  gameSuggestRenderKey = renderKey;
}

function applySelectedGameService(service, options = {}) {
  if (!service) {
    clearGameServiceSelection({ clearInput: false });
    return;
  }

  state.gameSelectedService = service;
  if (gameServiceInputEl && options.keepInput !== true) {
    gameServiceInputEl.value = service.option_label || `#${service.id} - ${service.name || ''}`;
  }
  if (gamePriceViewEl) {
    gamePriceViewEl.value = rupiah(service.sell_price || 0);
  }
  renderGameServiceInfo(service);
  if (options.hideSuggestions !== false) {
    hideGameServiceSuggestions();
  }
}

async function loadGameCategories(options = {}) {
  const force = !!options.force;
  if (!force && state.gameServicesLoaded && Array.isArray(state.gameServices) && state.gameServices.length > 0 && gameCategorySelectEl?.options?.length > 1) {
    return;
  }

  if (force && state.gameServiceSearchCache instanceof Map) {
    state.gameServiceSearchCache.clear();
  }

  const endpoint = force
    ? `./api/game_services.php?mode=categories&_t=${Date.now()}`
    : './api/game_services.php?mode=categories';
  const { data } = await apiRequest(endpoint, { forceRefresh: force });
  if (!data?.status) {
    state.gameServicesLoaded = false;
    if (gameOrderNoticeEl) {
      showNotice(gameOrderNoticeEl, 'err', data?.data?.msg || 'Gagal memuat kategori game.');
    }
    return;
  }

  const categories = Array.isArray(data?.data?.categories) ? data.data.categories : [];
  state.gameServices = categories;
  state.gameServicesLoaded = true;
  if (!gameCategorySelectEl) return;
  const currentValue = String(gameCategorySelectEl.value || '');
  gameCategorySelectEl.innerHTML = [
    '<option value="">Semua Kategori</option>',
    ...categories.map((category) => `<option value="${escapeHtml(category)}">${escapeHtml(category)}</option>`),
  ].join('');
  if (currentValue && categories.includes(currentValue)) {
    gameCategorySelectEl.value = currentValue;
  }
}

function trimGameSearchCache() {
  if (!(state.gameServiceSearchCache instanceof Map)) return;
  if (state.gameServiceSearchCache.size <= GAME_SEARCH_CACHE_MAX_KEYS) return;

  const keys = Array.from(state.gameServiceSearchCache.keys());
  const removeCount = state.gameServiceSearchCache.size - GAME_SEARCH_CACHE_MAX_KEYS;
  for (let i = 0; i < removeCount; i += 1) {
    state.gameServiceSearchCache.delete(keys[i]);
  }
}

async function searchGameServices(query, category = '', options = {}) {
  const force = !!options.force;
  const normalizedQuery = String(query || '').trim();
  const normalizedCategory = String(category || '').trim();
  const cacheKey = `${normalizedCategory}|${normalizeQuery(normalizedQuery)}`;

  if (!force && state.gameServiceSearchCache instanceof Map && state.gameServiceSearchCache.has(cacheKey)) {
    return state.gameServiceSearchCache.get(cacheKey) || [];
  }

  const params = new URLSearchParams({
    mode: 'search',
    q: normalizedQuery,
    limit: '40',
  });
  if (normalizedCategory) {
    params.set('category', normalizedCategory);
  }
  if (force) {
    params.set('_t', String(Date.now()));
  }

  const requestId = ++state.gameServicesRequestId;
  const { data } = await apiRequest(`./api/game_services.php?${params.toString()}`, {
    forceRefresh: force,
  });
  if (requestId !== state.gameServicesRequestId) {
    return [];
  }
  if (!data?.status) {
    return [];
  }

  const services = Array.isArray(data?.data?.services) ? data.data.services : [];
  if (state.gameServiceSearchCache instanceof Map) {
    state.gameServiceSearchCache.set(cacheKey, services);
    trimGameSearchCache();
  }
  return services;
}

async function resolveGameServiceByInput() {
  if (!gameServiceInputEl) return null;

  const raw = String(gameServiceInputEl.value || '').trim();
  if (raw === '') return null;

  if (state.gameSelectedService && (state.gameSelectedService.option_label === raw || `#${state.gameSelectedService.id}` === raw)) {
    return state.gameSelectedService;
  }

  const idMatch = raw.match(/^#?\s*([A-Za-z0-9_]+)/);
  const id = String(idMatch?.[1] || '').trim();
  if (id) {
    const { data } = await apiRequest(`./api/game_services.php?mode=detail&id=${encodeURIComponent(id)}`);
    if (data?.status) {
      return data?.data?.service || null;
    }
  }

  const category = String(gameCategorySelectEl?.value || '').trim();
  const candidates = await searchGameServices(raw, category, { force: false });
  if (!Array.isArray(candidates) || candidates.length === 0) {
    return null;
  }

  const exact = normalizeQuery(raw);
  const found = candidates.find((candidate) => {
    const optionLabel = normalizeQuery(candidate?.option_label || '');
    const nameLabel = normalizeQuery(candidate?.name || '');
    return optionLabel === exact || nameLabel === exact;
  });
  return found || candidates[0] || null;
}

async function refreshGameServiceSuggestions(options = {}) {
  if (!gameServiceInputEl) {
    return;
  }

  const force = !!options.force;
  const query = String(gameServiceInputEl.value || '').trim();
  const category = String(gameCategorySelectEl?.value || '').trim();

  if (query.length < GAME_QUERY_MIN_CHARS) {
    hideGameServiceSuggestions();
    return;
  }

  const services = await searchGameServices(query, category, { force });
  renderGameServiceSuggestions(services, query);
}

function normalizeGameContactInput(value) {
  let normalized = String(value || '').trim();
  if (!normalized) return '';
  normalized = normalized.replace(/[^\d+]/g, '');
  if (normalized.startsWith('+')) {
    normalized = normalized.slice(1);
  }
  if (normalized.startsWith('0')) {
    normalized = `62${normalized.slice(1)}`;
  }
  return normalized;
}

async function submitGameOrder() {
  if (!gameOrderFormEl) return;
  if (GAME_TOPUP_COMING_SOON) {
    renderGameComingSoonState();
    return;
  }

  const target = String(gameTargetInputEl?.value || '').trim();
  const contact = normalizeGameContactInput(gameContactInputEl?.value || '');

  if (!target) {
    showNotice(gameOrderNoticeEl, 'err', 'ID target game wajib diisi.');
    return;
  }
  if (!contact || !/^\d{8,20}$/.test(contact)) {
    showNotice(gameOrderNoticeEl, 'err', 'Kontak WhatsApp wajib diisi dengan format angka yang valid.');
    return;
  }

  let service = state.gameSelectedService;
  if (!service) {
    service = await resolveGameServiceByInput();
  }

  if (!service || !service.id) {
    showNotice(gameOrderNoticeEl, 'err', 'Layanan topup game tidak ditemukan. Pilih dari saran layanan.');
    return;
  }

  if ((service.is_active ?? true) === false) {
    showNotice(gameOrderNoticeEl, 'err', 'Layanan topup game sedang tidak aktif. Pilih layanan lain.');
    return;
  }

  state.gameSelectedService = service;
  applySelectedGameService(service, { hideSuggestions: true });
  hideNotice(gameOrderNoticeBottomEl);
  showNotice(gameOrderNoticeEl, 'info', 'Membuat checkout topup game...');

  const payload = {
    service_id: String(service.id),
    target,
    contact,
  };

  const { data } = await apiRequest('./api/game_order.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });

  if (!data?.status) {
    showNotice(gameOrderNoticeEl, 'err', data?.data?.msg || 'Checkout topup game gagal diproses.');
    return;
  }

  const info = (data.data && typeof data.data === 'object') ? data.data : {};
  const orderId = Number(info.order_id || 0);
  showNotice(
    gameOrderNoticeEl,
    'ok',
    `Checkout game berhasil dibuat.\nOrder ID: #${orderId || '-'}\nTotal Bayar: ${rupiah(info.total_sell_price || 0)}\nLanjutkan pembayaran via QR.`
  );

  if (gameTargetInputEl) gameTargetInputEl.value = '';
  if (gameContactInputEl) gameContactInputEl.value = '';
  clearGameServiceSelection({ clearInput: true });

  renderCheckoutPanel(info);
  await Promise.all([
    fetchSession({ attempts: SESSION_FETCH_RETRY_ATTEMPTS, softFail: true }),
    loadGameOrders({ force: true }),
    loadGameAdminPendingOrders({ force: true }),
  ]);
  updateHeaderStats();
  openPaymentQrModal(info);
}

async function verifyGamePaymentByAdmin(orderId, action, adminNote = '') {
  const normalizedOrderId = Number(orderId || 0);
  const normalizedAction = String(action || '').trim().toLowerCase();
  if (normalizedOrderId <= 0 || !['verify', 'cancel'].includes(normalizedAction)) {
    return false;
  }

  showNotice(gameAdminPendingNoticeEl, 'info', 'Memproses verifikasi pembayaran game...');
  const { data } = await apiRequest('./api/game_order_admin_verify.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      order_id: normalizedOrderId,
      action: normalizedAction,
      admin_note: String(adminNote || ''),
    }),
  });

  if (!data?.status) {
    showNotice(gameAdminPendingNoticeEl, 'err', data?.data?.msg || 'Gagal memproses verifikasi pembayaran game.');
    return false;
  }

  showNotice(gameAdminPendingNoticeEl, 'ok', data?.data?.msg || 'Verifikasi pembayaran game berhasil diproses.');
  await Promise.all([
    fetchSession({ attempts: SESSION_FETCH_RETRY_ATTEMPTS, softFail: true }),
    loadGameOrders({ force: true }),
    loadGameAdminPendingOrders({ force: true }),
  ]);
  updateHeaderStats();
  return true;
}

function normalizedGameStatus(status) {
  const value = String(status || '').trim();
  if (!value) return 'Menunggu Pembayaran';

  const key = value.toLowerCase();
  if (key.includes('menunggu pembayaran') || key.includes('waiting payment')) return 'Menunggu Pembayaran';
  if (key.includes('selesai') || key.includes('success') || key.includes('completed') || key.includes('done')) return 'Selesai';
  if (key.includes('dibatalkan') || key.includes('cancel') || key.includes('error') || key.includes('failed') || key.includes('refund')) return 'Dibatalkan';
  if (key.includes('diproses') || key.includes('pending') || key.includes('processing') || key.includes('progress')) return 'Diproses';
  return value;
}

function normalizedGameStatusKey(status) {
  return normalizedGameStatus(status).toUpperCase();
}

function renderGameOrders() {
  if (!gameOrdersBodyEl) return;

  const rows = Array.isArray(state.gameOrders) ? state.gameOrders : [];
  const statusFilter = String(state.gameHistoryFilter?.status || 'ALL').toUpperCase();
  const idQuery = String(state.gameHistoryFilter?.idQuery || '').trim();
  const serviceQuery = normalizeQuery(String(state.gameHistoryFilter?.serviceQuery || ''));

  const filtered = rows.filter((order) => {
    const status = normalizedGameStatus(order?.status || '');
    const statusKey = normalizedGameStatusKey(status);
    if (statusFilter !== 'ALL' && statusKey !== statusFilter) {
      return false;
    }
    if (idQuery && !String(order?.id || '').includes(idQuery)) {
      return false;
    }
    if (serviceQuery) {
      const haystack = normalizeQuery(`${order?.service_name || ''} ${order?.category || ''}`);
      if (!haystack.includes(serviceQuery)) {
        return false;
      }
    }
    return true;
  });

  if (gameHistorySummaryEl) {
    gameHistorySummaryEl.textContent = `Menampilkan ${filtered.length} data topup game`;
  }

  if (!filtered.length) {
    gameOrdersBodyEl.innerHTML = '<tr><td colspan="9">Belum ada order topup game.</td></tr>';
    return;
  }

  gameOrdersBodyEl.innerHTML = filtered.map((order) => {
    const status = normalizedGameStatus(order.status);
    const normalized = normalizeOrderStatus({ status });
    const providerOrder = String(order.provider_order_id || '-');
    return `
      <tr>
        <td>#${escapeHtml(order.id)}</td>
        <td>${escapeHtml(order.service_name || '-')}</td>
        <td>${escapeHtml(order.target || '-')}</td>
        <td>${escapeHtml(order.contact || '-')}</td>
        <td>${rupiah(order.total_sell_price || 0)}</td>
        <td><span class="status ${statusClass(normalized)}">${escapeHtml(displayOrderStatus(normalized))}</span></td>
        <td>${escapeHtml(providerOrder)}</td>
        <td>${escapeHtml(formatDateTime(order.created_at || order.updated_at || ''))}</td>
        <td>
          <button type="button" class="mini-btn ghost" data-game-action="status" data-game-order-id="${escapeHtml(order.id)}">Cek Status</button>
        </td>
      </tr>
    `;
  }).join('');
}

async function loadGameOrders(options = {}) {
  const force = !!options.force;
  if (!force && state.gameOrdersLoaded) {
    renderGameOrders();
    return;
  }

  const requestId = ++state.gameOrdersRequestId;
  const { data } = await apiRequest('./api/game_orders.php?limit=200', {
    forceRefresh: force,
  });
  if (requestId !== state.gameOrdersRequestId) {
    return;
  }

  if (!data?.status) {
    state.gameOrders = [];
    state.gameOrdersLoaded = false;
    renderGameOrders();
    if (gameOrderNoticeBottomEl) {
      showNotice(gameOrderNoticeBottomEl, 'err', data?.data?.msg || 'Gagal memuat riwayat topup game.');
    }
    return;
  }

  state.gameOrders = Array.isArray(data?.data?.orders) ? data.data.orders : [];
  state.gameOrdersLoaded = true;
  renderGameOrders();
}

function renderGameAdminPendingOrders() {
  if (!gameAdminPendingPanelEl || !gameAdminPendingBodyEl) return;

  const isAdmin = String(state.user?.role || '') === 'admin';
  gameAdminPendingPanelEl.classList.toggle('hidden', !isAdmin);
  if (!isAdmin) {
    return;
  }

  const rows = Array.isArray(state.gameAdminPendingOrders) ? state.gameAdminPendingOrders : [];
  if (!rows.length) {
    gameAdminPendingBodyEl.innerHTML = '<tr><td colspan="7">Tidak ada pembayaran game yang menunggu verifikasi.</td></tr>';
    return;
  }

  gameAdminPendingBodyEl.innerHTML = rows.map((order) => `
    <tr>
      <td>#${escapeHtml(order.id)}</td>
      <td>${escapeHtml(order.username || '-')}</td>
      <td>${escapeHtml(order.service_name || '-')}</td>
      <td>${rupiah(order.total_sell_price || 0)}</td>
      <td>${escapeHtml(order.payment_confirmed_at ? formatDateTime(order.payment_confirmed_at) : 'Belum konfirmasi')}</td>
      <td>${escapeHtml(formatDateTime(order.payment_deadline_at || ''))}</td>
      <td>
        <button type="button" class="mini-btn success" data-game-admin-action="verify" data-game-admin-order="${escapeHtml(order.id)}">Verifikasi</button>
        <button type="button" class="mini-btn danger" data-game-admin-action="cancel" data-game-admin-order="${escapeHtml(order.id)}">Batalkan</button>
      </td>
    </tr>
  `).join('');
}

async function loadGameAdminPendingOrders(options = {}) {
  const force = !!options.force;
  const isAdmin = String(state.user?.role || '') === 'admin';
  if (!isAdmin) {
    state.gameAdminPendingOrders = [];
    state.gameAdminPendingLoaded = false;
    renderGameAdminPendingOrders();
    return;
  }

  if (!force && state.gameAdminPendingLoaded) {
    renderGameAdminPendingOrders();
    return;
  }

  const requestId = ++state.gameAdminPendingRequestId;
  const { data } = await apiRequest('./api/game_order_admin_payments.php?status=waiting&limit=120', {
    forceRefresh: force,
  });
  if (requestId !== state.gameAdminPendingRequestId) {
    return;
  }

  if (!data?.status) {
    state.gameAdminPendingOrders = [];
    state.gameAdminPendingLoaded = false;
    renderGameAdminPendingOrders();
    if (gameAdminPendingNoticeEl) {
      showNotice(gameAdminPendingNoticeEl, 'err', data?.data?.msg || 'Gagal memuat verifikasi pembayaran game.');
    }
    return;
  }

  state.gameAdminPendingOrders = Array.isArray(data?.data?.orders) ? data.data.orders : [];
  state.gameAdminPendingLoaded = true;
  renderGameAdminPendingOrders();
}

async function requestGameOrderStatusUpdate(orderId) {
  const normalizedOrderId = Number(orderId || 0);
  if (!Number.isFinite(normalizedOrderId) || normalizedOrderId <= 0) {
    return {
      status: false,
      data: { msg: 'Order ID game tidak valid.' },
    };
  }

  const { data } = await apiRequest('./api/game_order_status.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ order_id: normalizedOrderId }),
  });

  return data || {
    status: false,
    data: { msg: 'Gagal mengecek status topup game.' },
  };
}

async function checkGameOrderStatus(orderId) {
  if (gameOrderNoticeBottomEl) {
    showNotice(gameOrderNoticeBottomEl, 'info', `Mengecek status topup game #${orderId}...`);
  }

  const data = await requestGameOrderStatusUpdate(orderId);
  if (!data?.status) {
    if (gameOrderNoticeBottomEl) {
      showNotice(gameOrderNoticeBottomEl, 'err', data?.data?.msg || 'Gagal mengecek status topup game.');
    }
    return;
  }

  if (gameOrderNoticeBottomEl) {
    showNotice(gameOrderNoticeBottomEl, 'ok', `Status order game #${orderId}: ${data?.data?.status || 'Diproses'}`);
  }

  await loadGameOrders({ force: true });
}

function focusGameHistory(orderId) {
  if (GAME_TOPUP_COMING_SOON) {
    applyPanelView('game');
    updateUrlForView('game');
    renderGameComingSoonState();
    return;
  }

  const normalizedOrderId = Number(orderId || 0);
  applyPanelView('game');
  updateUrlForView('game');

  state.gameHistoryFilter.status = 'Menunggu Pembayaran';
  state.gameHistoryFilter.idQuery = normalizedOrderId > 0 ? String(normalizedOrderId) : '';
  state.gameHistoryFilter.serviceQuery = '';
  if (gameHistoryStatusEl) {
    gameHistoryStatusEl.value = 'Menunggu Pembayaran';
  }
  if (gameHistoryIdSearchEl) {
    gameHistoryIdSearchEl.value = state.gameHistoryFilter.idQuery;
  }
  if (gameHistoryServiceSearchEl) {
    gameHistoryServiceSearchEl.value = '';
  }
  renderGameOrders();
  loadGameOrders({ force: true }).catch(() => {
    // Ignore background refresh error when focusing game history.
  });

  if (gameOrderNoticeBottomEl) {
    showNotice(
      gameOrderNoticeBottomEl,
      'info',
      normalizedOrderId > 0
        ? `Order game #${normalizedOrderId} menunggu konfirmasi admin untuk pembayaran.`
        : 'Order game menunggu konfirmasi admin untuk pembayaran.'
    );
  }

  if (gameSectionEl) {
    scrollElementIntoView(gameSectionEl, 'start');
  }
}

async function ensureViewData(view, options = {}) {
  const force = !!options.force;
  const normalizedView = normalizePanelView(view);
  const isAdmin = String(state.user?.role || '') === 'admin';

  switch (normalizedView) {
    case 'utama':
      await Promise.all([
        loadPanelHighlights({ force }),
        loadTop5Services({ force }),
      ]);
      break;
    case 'dashboard':
      await Promise.all([
        loadNews({ force }),
        loadPanelHighlights({ force }),
        loadTop5Services({ force }),
      ]);
      if (isAdmin) {
        await loadAdminNews({ force });
      }
      break;
    case 'profile':
      updateProfilePanel();
      break;
    case 'top5':
      await loadTop5Services({ force });
      break;
    case 'purchase':
      await Promise.all([
        loadServices({ force }),
        loadOrders({ force }),
      ]);
      break;
    case 'refill':
      await loadRefills({ force });
      break;
    case 'game':
      if (GAME_TOPUP_COMING_SOON) {
        renderGameComingSoonState();
        break;
      }
      await Promise.all([
        loadGameCategories({ force }),
        loadGameOrders({ force }),
        loadGameAdminPendingOrders({ force }),
      ]);
      break;
    case 'deposit':
      await Promise.all([
        loadDepositHistory({ force }),
        loadAdminDeposits({ force }),
      ]);
      break;
    case 'services':
      await Promise.all([
        loadServices({ force }),
        loadServicesCatalog({ force }),
      ]);
      break;
    case 'ticket':
      await loadTickets({ force });
      break;
    case 'admin':
      if (isAdmin) {
        await Promise.all([
          loadAdminPaymentOrders({ force }),
          loadAdminOrderHistory({ force }),
          loadAdminNews({ force }),
          loadAdminCsChats({ force }),
        ]);
      }
      break;
    default:
      break;
  }
}

async function refreshDashboard() {
  hideNotice(orderNotice);
  hideNotice(ordersNotice);
  if (refillNoticeEl) hideNotice(refillNoticeEl);
  if (refillStatusNoticeEl) hideNotice(refillStatusNoticeEl);
  if (gameOrderNoticeEl) hideNotice(gameOrderNoticeEl);
  if (gameOrderNoticeBottomEl) hideNotice(gameOrderNoticeBottomEl);
  if (gameAdminPendingNoticeEl) hideNotice(gameAdminPendingNoticeEl);
  if (newsNoticeEl) hideNotice(newsNoticeEl);
  const loggedIn = await fetchSession({
    attempts: SESSION_FETCH_RETRY_ATTEMPTS,
    softFail: true,
  });
  if (!loggedIn) return;

  await ensureViewData(state.currentView || 'utama', { force: true });

  applyPanelView(state.currentView || 'utama');
}

if (tabLogin) {
  tabLogin.addEventListener('click', () => switchAuthTab('login'));
}
if (tabRegister) {
  tabRegister.addEventListener('click', () => switchAuthTab('register'));
}

panelNavLinks.forEach((link) => {
  link.addEventListener('click', async (event) => {
    const nextView = normalizePanelView(link.dataset.view || '');
    event.preventDefault();
    if (nextView === state.currentView) {
      return;
    }
    applyPanelView(nextView);
    updateUrlForView(nextView);
    if (state.user) {
      await ensureViewData(nextView, { force: false });
    }
  });
});

quickViewButtons.forEach((button) => {
  button.addEventListener('click', async () => {
    const nextView = normalizePanelView(button.dataset.quickView || '');
    if (!nextView) return;
    if (nextView === state.currentView) {
      return;
    }
    applyPanelView(nextView);
    updateUrlForView(nextView);
    if (state.user) {
      await ensureViewData(nextView, { force: false });
    }
  });
});

if (shareNativeBtnEl) {
  shareNativeBtnEl.addEventListener('click', async () => {
    await handleNativeShare();
  });
}

if (shareCopyBtnEl) {
  shareCopyBtnEl.addEventListener('click', async () => {
    const payload = getSharePayload();
    const copied = await copyTextToClipboard(payload.fullText);
    if (copied) {
      showNotice(shareNoticeEl, 'ok', 'Link website berhasil disalin.');
      return;
    }

    showNotice(shareNoticeEl, 'err', 'Gagal menyalin link website.');
  });
}

if (shareProviderButtons.length) {
  shareProviderButtons.forEach((button) => {
    button.addEventListener('click', async () => {
      const provider = String(button.dataset.shareProvider || '').trim().toLowerCase();
      if (!provider) return;
      await handleShareProvider(provider);
    });
  });
}

if (panelInfoRefreshBtnEl) {
  panelInfoRefreshBtnEl.addEventListener('click', async () => {
    setPanelInfoClosed(false);
    await Promise.all([
      loadPanelHighlights({ force: true }),
      loadTop5Services({ force: true }),
    ]);
  });
}

if (panelInfoCloseBtnEl) {
  panelInfoCloseBtnEl.addEventListener('click', () => {
    setPanelInfoClosed(!state.panelInfoClosed);
  });
}

if (accountMenuToggleEl) {
  accountMenuToggleEl.addEventListener('click', (event) => {
    event.preventDefault();
    const expanded = accountMenuToggleEl.getAttribute('aria-expanded') === 'true';
    setAccountMenuOpen(!expanded);
  });
}

if (btnOpenProfileEl) {
  btnOpenProfileEl.addEventListener('click', async (event) => {
    event.preventDefault();
    closeAccountMenu();
    await openProfileView({ focusSettings: false });
  });
}

if (btnOpenSettingsEl) {
  btnOpenSettingsEl.addEventListener('click', async (event) => {
    event.preventDefault();
    closeAccountMenu();
    await openProfileView({ focusSettings: true });
  });
}

async function completeAuthFlowAfterSuccess(preferredView = 'utama') {
  const targetView = normalizePanelView(preferredView || 'utama');
  const loggedIn = await fetchSession({
    attempts: SESSION_FETCH_RETRY_ATTEMPTS,
    softFail: false,
  });

  if (!loggedIn) {
    showNotice(authNotice, 'err', 'Login sukses tetapi sesi belum terbaca. Coba muat ulang halaman.');
    return false;
  }

  hideNotice(authNotice);
  closeAccountMenu();
  applyPanelView(targetView);
  updateUrlForView(targetView);
  await ensureViewData(targetView, { force: false });
  return true;
}

if (loginForm) {
  loginForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    showNotice(authNotice, 'info', 'Memproses login...');

    const loginIdentityEl = document.getElementById('loginIdentity');
    const payload = {
      identity: String(loginIdentityEl?.value || '').trim(),
      password: String(loginPasswordEl?.value || ''),
    };

    const { data } = await apiRequest('./api/auth_login.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    if (!data.status) {
      showNotice(authNotice, 'err', data?.data?.msg || 'Login gagal.');
      return;
    }

    showNotice(authNotice, 'ok', 'Login berhasil. Menyiapkan dashboard...');
    await completeAuthFlowAfterSuccess('utama');
  });
}

if (registerForm) {
  registerForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    showNotice(authNotice, 'info', 'Membuat akun...');

    const regUsernameEl = document.getElementById('regUsername');
    const regPasswordEl = document.getElementById('regPassword');
    const username = String(regUsernameEl?.value || '').trim();
    const password = String(regPasswordEl?.value || '');

    if (!/^[a-zA-Z0-9_]{4,30}$/.test(username)) {
      showNotice(authNotice, 'err', 'Username minimal 4 karakter, hanya huruf/angka/underscore.');
      return;
    }

    if (String(password).length < 6) {
      showNotice(authNotice, 'err', 'Password minimal 6 karakter.');
      return;
    }

    const payload = {
      username,
      password,
    };

    const { data } = await apiRequest('./api/auth_register.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    if (!data.status) {
      showNotice(authNotice, 'err', data?.data?.msg || 'Registrasi gagal.');
      return;
    }

    showNotice(authNotice, 'ok', 'Registrasi berhasil. Menyiapkan dashboard...');
    await completeAuthFlowAfterSuccess('utama');
  });
}

if (loginPasswordEl && loginPasswordToggleEl) {
  syncLoginPasswordToggleState();
  loginPasswordToggleEl.addEventListener('click', () => {
    const shouldShow = loginPasswordEl.type === 'password';
    loginPasswordEl.type = shouldShow ? 'text' : 'password';
    syncLoginPasswordToggleState();
    try {
      loginPasswordEl.focus({ preventScroll: true });
    } catch {
      loginPasswordEl.focus();
    }
  });
}

if (orderFormEl) {
  orderFormEl.addEventListener('submit', async (event) => {
    event.preventDefault();

    const targetValue = targetEl?.value?.trim() || '';
    if (!targetValue) {
      showNotice(orderNotice, 'err', 'Target wajib diisi terlebih dahulu.');
      return;
    }

    let service = selectedService();

    if (!service) {
      const fallbackMatch = String(serviceInputEl?.value || '').trim().match(/^#?\s*(\d+)/);
      const fallbackId = Number(fallbackMatch?.[1] || 0);
      if (fallbackId > 0) {
        const { data: detailData } = await apiRequest(
          `./api/services.php?mode=detail&variant=${encodeURIComponent(DEFAULT_SERVICE_VARIANT)}&id=${fallbackId}`
        );
        if (detailData?.status && detailData?.data?.service) {
          service = detailData.data.service;
          state.services = [service];
          buildServiceIndex();
          state.selectedServiceId = Number(service.id || 0);
          serviceInputEl.value = serviceOptionLabel(service);
        }
      }
    }

    if (!service) {
      const fallbackName = normalizeQuery(String(serviceInputEl?.value || ''));
      if (fallbackName.length >= 2) {
        const params = new URLSearchParams({
          mode: 'search',
          variant: DEFAULT_SERVICE_VARIANT,
          q: fallbackName,
          limit: '5',
        });
        const categoryName = getSelectedCategoryName();
        if (categoryName) {
          params.set('category', categoryName);
        }

        const { data: searchData } = await apiRequest(`./api/services.php?${params.toString()}`);
        const firstService = Array.isArray(searchData?.data?.services) && searchData.data.services.length > 0
          ? searchData.data.services[0]
          : null;

        if (firstService) {
          service = firstService;
          state.services = [service];
          buildServiceIndex();
          state.selectedServiceId = Number(service.id || 0);
          serviceInputEl.value = serviceOptionLabel(service);
        }
      }
    }

    if (!service) {
      showNotice(orderNotice, 'err', 'Layanan tidak ditemukan. Pilih dari dropdown saran layanan.');
      return;
    }

    // Kategori tidak lagi wajib dipilih manual; jika kosong, turunkan dari layanan terpilih.
    if (!getSelectedCategoryName() && categoryInputEl) {
      categoryInputEl.value = String(service.category || 'Lainnya');
      fillCategoryOptions();
    }

    if (isCommentService(service)) {
      const commentLines = normalizeLines(komenEl.value || commentsEl.value);

      if (!commentLines.length) {
        showNotice(orderNotice, 'err', 'Komentar wajib diisi untuk layanan ini.');
        return;
      }
    }

    if (isMentionsCustomListService(service)) {
      const mentionLines = normalizeLines(usernamesEl.value || komenEl.value);
      if (!mentionLines.length) {
        showNotice(orderNotice, 'err', 'Usernames wajib diisi untuk layanan mention custom list.');
        return;
      }
    }

    const isAutoQuantityService = isCommentService(service) || isMentionsCustomListService(service);
    const qtyValue = Number(String(quantityEl?.value || '').replace(/\D+/g, '')) || 0;
    if (!isAutoQuantityService && qtyValue <= 0) {
      showNotice(orderNotice, 'err', 'Jumlah (quantity) wajib diisi dengan angka yang valid.');
      return;
    }

    const payload = {
      service: Number(service.id),
      data: targetValue,
      quantity: quantityEl.value,
      komen: komenEl.value,
      comments: commentsEl.value,
      usernames: usernamesEl.value,
      username: singleUsernameEl.value.trim(),
      hashtags: hashtagsEl.value,
      keywords: keywordsEl.value.trim(),
    };

    showNotice(orderNotice, 'info', 'Membuat checkout order...');

    const { data } = await apiRequest('./api/order.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    if (!data.status) {
      showNotice(orderNotice, 'err', data?.data?.msg || 'Order gagal diproses.');
      return;
    }

    const info = data.data || {};
    showNotice(orderNotice, 'ok', `Checkout berhasil dibuat.\nOrder ID: #${info.order_id}\nTotal Bayar: ${rupiah(info.total_sell_price || 0)}\nLanjutkan pembayaran via QR.`);
    renderCheckoutPanel(info);

    quantityEl.value = '';
    komenEl.value = '';
    commentsEl.value = '';
    usernamesEl.value = '';
    singleUsernameEl.value = '';
    hashtagsEl.value = '';
    keywordsEl.value = '';

    await Promise.all([
      fetchSession({ attempts: SESSION_FETCH_RETRY_ATTEMPTS, softFail: true }),
      loadOrders({ force: true }),
      loadAdminPaymentOrders({ force: true }),
    ]);
    updateHeaderStats();
    openPaymentQrModal(info);
  });
}

if (gameOrderFormEl) {
  gameOrderFormEl.addEventListener('submit', async (event) => {
    event.preventDefault();
    await submitGameOrder();
  });
}

if (paymentConfirmBtnEl) {
  paymentConfirmBtnEl.addEventListener('click', async () => {
    await submitPaymentConfirmation('panel');
  });
}

if (depositFormEl) {
  depositFormEl.addEventListener('submit', async (event) => {
    event.preventDefault();

    const amount = Number(String(depositAmountEl?.value || '').replace(/\D+/g, '')) || 0;
    if (amount <= 0) {
      showNotice(depositNoticeEl, 'err', 'Nominal deposit wajib diisi.');
      return;
    }

    showNotice(depositNoticeEl, 'info', 'Membuat permintaan deposit...');

    const payload = {
      amount,
      payer_name: depositPayerNameEl?.value?.trim() || '',
      payer_note: depositPayerNoteEl?.value || '',
    };

    const { data } = await apiRequest('./api/deposit_create.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    if (!data.status) {
      showNotice(depositNoticeEl, 'err', data?.data?.msg || 'Gagal membuat permintaan deposit.');
      return;
    }

    const info = data.data?.deposit || {};
    showNotice(
      depositNoticeEl,
      'ok',
      `Permintaan deposit #${info.id || '-'} berhasil dibuat.\nNominal transfer: ${rupiah(info.amount_final || 0)}\nSilakan transfer lalu tunggu admin verifikasi.`
    );

    if (depositAmountEl) depositAmountEl.value = '';
    if (depositPayerNameEl) depositPayerNameEl.value = '';
    if (depositPayerNoteEl) depositPayerNoteEl.value = '';

    await Promise.all([loadDepositHistory(), loadAdminDeposits()]);
  });
}

if (newsListEl) {
  newsListEl.addEventListener('click', (event) => {
    const button = event.target.closest('[data-read-news]');
    if (!button) return;
    const newsId = String(button.dataset.readNews || '').trim();
    if (!newsId) return;

    openNewsModal(newsId);
  });
}

if (newsModalCloseEl) {
  newsModalCloseEl.addEventListener('click', closeNewsModal);
}

if (newsModalEl) {
  newsModalEl.addEventListener('click', (event) => {
    if (event.target === newsModalEl) {
      closeNewsModal();
    }
  });
}

if (paymentQrModalCloseEl) {
  paymentQrModalCloseEl.addEventListener('click', closePaymentQrModal);
}

if (paymentQrModalEl) {
  paymentQrModalEl.addEventListener('click', (event) => {
    if (event.target === paymentQrModalEl) {
      closePaymentQrModal();
    }
  });
}

if (paymentQrToHistoryEl) {
  paymentQrToHistoryEl.addEventListener('click', () => {
    closePaymentQrModal();
    const orderType = normalizeCheckoutOrderType(state.lastCheckout?.order_type || 'ssm');
    if (orderType === 'game') {
      focusGameHistory(state.lastCheckout?.order_id || 0);
    } else {
      focusOrderHistory(state.lastCheckout?.order_id || 0);
    }
  });
}

if (paymentQrConfirmBtnEl) {
  paymentQrConfirmBtnEl.addEventListener('click', async () => {
    await submitPaymentConfirmation('modal');
  });
}

document.addEventListener('click', (event) => {
  const target = event.target;
  if (!(target instanceof Element)) return;

  if (accountMenuEl && accountMenuPanelEl && !accountMenuEl.contains(target)) {
    closeAccountMenu();
  }

  if (csChatWidgetEl && isCsChatPanelOpen() && !csChatWidgetEl.contains(target)) {
    setCsChatPanelOpen(false);
  }

  if (!isSuggestionInteractiveTarget(target)) {
    hideAllSuggestions();
  }
});

document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') {
    hideAllSuggestions();
    closeAccountMenu();
    closeNewsModal();
    closePaymentQrModal();
    setCsChatPanelOpen(false);
  }
});

document.addEventListener('visibilitychange', () => {
  if (document.hidden) {
    return;
  }

  const isAdmin = String(state.user?.role || '') === 'admin';
  if (isAdmin) {
    loadAdminPaymentOrders({ force: true, silent: true, detectNew: true }).catch(() => {
      // Ignore foreground resume polling error.
    });
    loadAdminCsChats({ force: true, silent: true }).catch(() => {
      // Ignore foreground CS admin polling error.
    });
  }

  syncPendingPaymentOrders({ silent: true, detectProgress: true }).catch(() => {
    // Ignore foreground resume sync error.
  });
  pollCsChat({ force: true }).catch(() => {
    // Ignore foreground CS poll error.
  });
});

if (newsResetBtnEl) {
  newsResetBtnEl.addEventListener('click', () => {
    resetNewsForm();
    if (newsNoticeEl) hideNotice(newsNoticeEl);
  });
}

if (newsFormEl) {
  newsFormEl.addEventListener('submit', async (event) => {
    event.preventDefault();

    const payload = {
      id: newsIdEl?.value || '',
      title: newsTitleEl?.value?.trim() || '',
      summary: newsSummaryEl?.value || '',
      content: newsContentEl?.value || '',
      source_name: sanitizeNewsSourceName(newsSourceNameEl?.value?.trim() || NEWS_SOURCE_BRAND),
      source_url: sanitizeNewsSourceUrl(newsSourceUrlEl?.value?.trim() || ''),
      is_published: !!newsIsPublishedEl?.checked,
      published_at: newsPublishedAtEl?.value || '',
    };

    if (!payload.title || !payload.summary || !payload.content) {
      showNotice(newsNoticeEl, 'err', 'Judul, ringkasan, dan konten berita wajib diisi.');
      return;
    }

    showNotice(newsNoticeEl, 'info', 'Menyimpan berita...');

    const { data } = await apiRequest('./api/news_admin_save.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    if (!data.status) {
      showNotice(newsNoticeEl, 'err', data?.data?.msg || 'Gagal menyimpan berita.');
      return;
    }

    showNotice(newsNoticeEl, 'ok', data?.data?.msg || 'Berita berhasil disimpan.');
    resetNewsForm();
    await Promise.all([loadNews({ force: true }), loadAdminNews({ force: true })]);
  });
}

if (profilePasswordFormEl) {
  profilePasswordFormEl.addEventListener('submit', async (event) => {
    event.preventDefault();
    await saveAccountSettings();
  });
}

if (ticketFormEl) {
  ticketFormEl.addEventListener('submit', async (event) => {
    event.preventDefault();
    await createTicket();
  });
}

if (ticketRefreshBtnEl) {
  ticketRefreshBtnEl.addEventListener('click', async () => {
    await loadTickets({ force: true });
  });
}

if (ticketStatusFilterEl) {
  ticketStatusFilterEl.addEventListener('change', async () => {
    state.ticketFilter.status = String(ticketStatusFilterEl.value || 'all').toLowerCase();
    clearTicketDetail();
    await loadTickets({ force: true });
  });
}

if (ticketSearchInputEl) {
  const debouncedTicketSearch = debounce(async () => {
    state.ticketFilter.query = ticketSearchInputEl.value || '';
    await loadTickets({ force: true });
  }, 220);
  ticketSearchInputEl.addEventListener('input', () => {
    debouncedTicketSearch();
  });
}

if (ticketBodyEl) {
  ticketBodyEl.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-open-ticket]');
    if (!button) return;
    const ticketId = Number(button.dataset.openTicket || 0);
    if (ticketId <= 0) return;
    await loadTicketDetail(ticketId);
  });
}

if (ticketReplyBtnEl) {
  ticketReplyBtnEl.addEventListener('click', async () => {
    await replyTicket();
  });
}

if (ticketCloseBtnEl) {
  ticketCloseBtnEl.addEventListener('click', async () => {
    await updateTicketStatus('close');
  });
}

if (ticketReopenBtnEl) {
  ticketReopenBtnEl.addEventListener('click', async () => {
    await updateTicketStatus('reopen');
  });
}

if (ticketCloseDetailBtnEl) {
  ticketCloseDetailBtnEl.addEventListener('click', () => {
    clearTicketDetail();
  });
}

if (csChatToggleEl) {
  csChatToggleEl.addEventListener('click', async (event) => {
    event.preventDefault();
    const nextOpen = !isCsChatPanelOpen();
    if (nextOpen) {
      await ensureCsChatBootstrap({ force: false, silent: false });
    }
    setCsChatPanelOpen(nextOpen);
    renderCsChat();
  });
}

if (csChatCloseBtnEl) {
  csChatCloseBtnEl.addEventListener('click', () => {
    setCsChatPanelOpen(false);
  });
}

if (csChatFormEl) {
  csChatFormEl.addEventListener('submit', async (event) => {
    event.preventDefault();
    await sendCsChatMessage();
  });
}

if (csChatTakeoverBtnEl) {
  csChatTakeoverBtnEl.addEventListener('click', async () => {
    const ticketId = Number(state.csChat?.ticketId || 0);
    if (ticketId <= 0) return;
    await adminTakeoverCsChat(ticketId);
    renderCsChat();
  });
}

if (adminCsRefreshBtnEl) {
  adminCsRefreshBtnEl.addEventListener('click', async () => {
    showNotice(adminCsNoticeEl, 'info', 'Memuat ulang chat customer service...');
    await loadAdminCsChats({ force: true, silent: false });
    showNotice(adminCsNoticeEl, 'ok', 'Data chat customer service berhasil diperbarui.');
  });
}

if (adminCsBodyEl) {
  adminCsBodyEl.addEventListener('click', async (event) => {
    const openBtn = event.target.closest('[data-admin-cs-open]');
    if (openBtn) {
      const ticketId = Number(openBtn.dataset.adminCsOpen || 0);
      if (ticketId > 0) {
        await openAdminCsTicket(ticketId);
      }
      return;
    }

    const takeoverBtn = event.target.closest('[data-admin-cs-takeover]');
    if (takeoverBtn) {
      const ticketId = Number(takeoverBtn.dataset.adminCsTakeover || 0);
      if (ticketId > 0) {
        await adminTakeoverCsChat(ticketId);
      }
    }
  });
}

if (categoryInputEl) {
  const debouncedFillCategoryOptions = debounce(fillCategoryOptions, CATEGORY_INPUT_DEBOUNCE_MS);
  categoryInputEl.addEventListener('input', debouncedFillCategoryOptions);
  categoryInputEl.addEventListener('change', fillCategoryOptions);
  categoryInputEl.addEventListener('focus', () => {
    hideServiceSuggestions();
    keepSuggestionInputVisible(categoryInputEl);
    fillCategoryOptions();
  });
}

if (serviceInputEl) {
  const debouncedFillServiceOptions = debounce(() => {
    fillServiceOptions().catch(() => {
      // Ignore suggestion refresh errors while typing.
    });
  }, SERVICE_INPUT_DEBOUNCE_MS);
  serviceInputEl.addEventListener('input', () => {
    state.selectedServiceId = 0;
    debouncedFillServiceOptions();
  });
  serviceInputEl.addEventListener('change', () => {
    fillServiceOptions().catch(() => {
      // Ignore suggestion refresh errors on change.
    });
  });
  serviceInputEl.addEventListener('focus', () => {
    hideCategorySuggestions();
    keepSuggestionInputVisible(serviceInputEl);
    fillServiceOptions({ force: false }).catch(() => {
      // Ignore suggestion refresh errors on focus.
    });
  });
}

if (gameCategorySelectEl) {
  gameCategorySelectEl.addEventListener('change', () => {
    clearGameServiceSelection({ clearInput: true });
    hideGameServiceSuggestions();
    if (gameServiceInputEl && String(gameServiceInputEl.value || '').trim().length >= GAME_QUERY_MIN_CHARS) {
      refreshGameServiceSuggestions({ force: false }).catch(() => {
        // Ignore suggestion refresh errors after category change.
      });
    }
  });
}

if (gameServiceInputEl) {
  const debouncedGameServiceSearch = debounce(() => {
    refreshGameServiceSuggestions({ force: false }).catch(() => {
      // Ignore game suggestion refresh errors while typing.
    });
  }, GAME_SEARCH_DEBOUNCE_MS);

  gameServiceInputEl.addEventListener('input', () => {
    state.gameSelectedService = null;
    debouncedGameServiceSearch();
  });
  gameServiceInputEl.addEventListener('change', () => {
    refreshGameServiceSuggestions({ force: false }).catch(() => {
      // Ignore game suggestion refresh errors on change.
    });
  });
  gameServiceInputEl.addEventListener('focus', () => {
    hideCategorySuggestions();
    hideServiceSuggestions();
    keepSuggestionInputVisible(gameServiceInputEl);
    refreshGameServiceSuggestions({ force: false }).catch(() => {
      // Ignore game suggestion refresh errors on focus.
    });
  });
}

if (categorySuggestPanelEl) {
  categorySuggestPanelEl.addEventListener('mousedown', (event) => {
    const handled = handleSuggestPanelClick(event, 'category');
    if (handled) {
      event.preventDefault();
    }
  });
  categorySuggestPanelEl.addEventListener('touchstart', (event) => {
    const handled = handleSuggestPanelClick(event, 'category');
    if (handled) {
      event.preventDefault();
    }
  }, { passive: false });
  categorySuggestPanelEl.addEventListener('click', (event) => {
    handleSuggestPanelClick(event, 'category');
  });
}

if (serviceSuggestPanelEl) {
  serviceSuggestPanelEl.addEventListener('mousedown', (event) => {
    const handled = handleSuggestPanelClick(event, 'service');
    if (handled) {
      event.preventDefault();
    }
  });
  serviceSuggestPanelEl.addEventListener('touchstart', (event) => {
    const handled = handleSuggestPanelClick(event, 'service');
    if (handled) {
      event.preventDefault();
    }
  }, { passive: false });
  serviceSuggestPanelEl.addEventListener('click', (event) => {
    handleSuggestPanelClick(event, 'service');
  });
}

if (gameServiceSuggestPanelEl) {
  gameServiceSuggestPanelEl.addEventListener('mousedown', (event) => {
    const handled = handleSuggestPanelClick(event, 'game-service');
    if (handled) {
      event.preventDefault();
    }
  });
  gameServiceSuggestPanelEl.addEventListener('touchstart', (event) => {
    const handled = handleSuggestPanelClick(event, 'game-service');
    if (handled) {
      event.preventDefault();
    }
  }, { passive: false });
  gameServiceSuggestPanelEl.addEventListener('click', (event) => {
    handleSuggestPanelClick(event, 'game-service');
  });
}

if (typeof window !== 'undefined') {
  const refreshSuggestionPanelPosition = debounce(() => {
    if (!hasOpenSuggestionPanel()) {
      return;
    }
    refreshOpenSuggestionPanelPosition();
  }, IS_IOS_DEVICE ? 90 : 50);

  window.addEventListener('resize', refreshSuggestionPanelPosition, { passive: true });
  window.addEventListener('scroll', refreshSuggestionPanelPosition, { passive: true });

  if (window.visualViewport && typeof window.visualViewport.addEventListener === 'function') {
    window.visualViewport.addEventListener('resize', refreshSuggestionPanelPosition);
    window.visualViewport.addEventListener('scroll', refreshSuggestionPanelPosition);
  }
}

if (quantityEl) {
  quantityEl.addEventListener('input', scheduleServiceInfoUpdate);
}

[komenEl, commentsEl, usernamesEl].forEach((el) => {
  if (!el) return;
  el.addEventListener('input', scheduleServiceInfoUpdate);
});

if (serviceCatalogSearchEl) {
  const debouncedCatalogSearch = debounce(() => {
    state.serviceCatalog.query = serviceCatalogSearchEl.value || '';
    state.serviceCatalog.page = 1;
    loadServicesCatalog({ force: false });
  }, CATALOG_INPUT_DEBOUNCE_MS);
  serviceCatalogSearchEl.addEventListener('input', () => {
    debouncedCatalogSearch();
  });
}

if (serviceCatalogCategoryEl) {
  serviceCatalogCategoryEl.addEventListener('change', () => {
    state.serviceCatalog.category = serviceCatalogCategoryEl.value || '';
    state.serviceCatalog.page = 1;
    loadServicesCatalog({ force: false });
  });
}

if (serviceCatalogSortByEl) {
  serviceCatalogSortByEl.addEventListener('change', () => {
    state.serviceCatalog.sortBy = serviceCatalogSortByEl.value || 'category_name';
    state.serviceCatalog.page = 1;
    loadServicesCatalog({ force: false });
  });
}

if (serviceCatalogSortDirEl) {
  serviceCatalogSortDirEl.addEventListener('change', () => {
    state.serviceCatalog.sortDir = serviceCatalogSortDirEl.value === 'desc' ? 'desc' : 'asc';
    state.serviceCatalog.page = 1;
    loadServicesCatalog({ force: false });
  });
}

if (servicesCatalogPerPageEl) {
  servicesCatalogPerPageEl.addEventListener('change', () => {
    state.serviceCatalog.perPage = Number(servicesCatalogPerPageEl.value || 50);
    state.serviceCatalog.page = 1;
    loadServicesCatalog({ force: false });
  });
}

if (servicesCatalogPaginationEl) {
  servicesCatalogPaginationEl.addEventListener('click', (event) => {
    const button = event.target.closest('[data-services-page]');
    if (!button) return;

    const nextPage = Number(button.dataset.servicesPage || 1);
    if (!Number.isFinite(nextPage) || nextPage <= 0) return;

    state.serviceCatalog.page = nextPage;
    loadServicesCatalog({ force: false });
  });
}

if (historyStatusTabsEl) {
  historyStatusTabsEl.addEventListener('click', (event) => {
    const button = event.target.closest('[data-status]');
    if (!button) return;

    state.history.status = button.dataset.status || 'ALL';
    state.history.page = 1;
    renderOrders();
  });
}

if (historyOrderIdSearchEl) {
  const debouncedHistoryIdFilter = debounce(() => {
    state.history.page = 1;
    renderOrders();
  }, 160);
  historyOrderIdSearchEl.addEventListener('input', () => {
    state.history.idQuery = historyOrderIdSearchEl.value || '';
    debouncedHistoryIdFilter();
  });
}

if (historyTargetSearchEl) {
  const debouncedHistoryTargetFilter = debounce(() => {
    state.history.page = 1;
    renderOrders();
  }, 180);
  historyTargetSearchEl.addEventListener('input', () => {
    state.history.targetQuery = historyTargetSearchEl.value || '';
    debouncedHistoryTargetFilter();
  });
}

if (historyServiceSearchEl) {
  const debouncedHistoryServiceFilter = debounce(() => {
    state.history.page = 1;
    renderOrders();
  }, 180);
  historyServiceSearchEl.addEventListener('input', () => {
    state.history.serviceQuery = historyServiceSearchEl.value || '';
    debouncedHistoryServiceFilter();
  });
}

if (historyPerPageEl) {
  historyPerPageEl.addEventListener('change', () => {
    state.history.perPage = Number(historyPerPageEl.value || 10);
    state.history.page = 1;
    renderOrders();
  });
}

if (ordersBody) {
  ordersBody.addEventListener('click', async (event) => {
    const target = event.target.closest('[data-check-order]');
    if (!target) return;

    const orderId = Number(target.dataset.checkOrder || 0);
    if (!orderId) return;

    await checkOrderStatus(orderId);
  });
}

if (gameHistoryStatusEl) {
  gameHistoryStatusEl.addEventListener('change', () => {
    state.gameHistoryFilter.status = gameHistoryStatusEl.value || 'ALL';
    renderGameOrders();
  });
}

if (gameHistoryIdSearchEl) {
  const debouncedGameHistoryId = debounce(() => {
    renderGameOrders();
  }, 150);
  gameHistoryIdSearchEl.addEventListener('input', () => {
    state.gameHistoryFilter.idQuery = gameHistoryIdSearchEl.value || '';
    debouncedGameHistoryId();
  });
}

if (gameHistoryServiceSearchEl) {
  const debouncedGameHistoryService = debounce(() => {
    renderGameOrders();
  }, 180);
  gameHistoryServiceSearchEl.addEventListener('input', () => {
    state.gameHistoryFilter.serviceQuery = gameHistoryServiceSearchEl.value || '';
    debouncedGameHistoryService();
  });
}

if (gameHistoryRefreshBtnEl) {
  gameHistoryRefreshBtnEl.addEventListener('click', async () => {
    showNotice(gameOrderNoticeBottomEl, 'info', 'Memuat ulang riwayat topup game...');
    await Promise.all([
      loadGameOrders({ force: true }),
      loadGameAdminPendingOrders({ force: true }),
    ]);
    showNotice(gameOrderNoticeBottomEl, 'ok', 'Riwayat topup game berhasil diperbarui.');
  });
}

if (gameOrdersBodyEl) {
  gameOrdersBodyEl.addEventListener('click', async (event) => {
    const statusBtn = event.target.closest('[data-game-action="status"]');
    if (!statusBtn) return;
    const orderId = Number(statusBtn.dataset.gameOrderId || 0);
    if (!Number.isFinite(orderId) || orderId <= 0) return;
    await checkGameOrderStatus(orderId);
  });
}

if (gameAdminPendingBodyEl) {
  gameAdminPendingBodyEl.addEventListener('click', async (event) => {
    const actionBtn = event.target.closest('[data-game-admin-action]');
    if (!actionBtn) return;

    const action = String(actionBtn.dataset.gameAdminAction || '').trim().toLowerCase();
    const orderId = Number(actionBtn.dataset.gameAdminOrder || 0);
    if (!Number.isFinite(orderId) || orderId <= 0 || !['verify', 'cancel'].includes(action)) {
      return;
    }

    const promptText = action === 'verify'
      ? 'Catatan admin verifikasi (opsional):'
      : 'Alasan pembatalan (opsional):';
    const adminNote = window.prompt(promptText, '');
    if (adminNote === null) return;

    await verifyGamePaymentByAdmin(orderId, action, adminNote);
  });
}

if (refillFormEl) {
  refillFormEl.addEventListener('submit', async (event) => {
    event.preventDefault();
    const orderId = Number(String(refillOrderIdEl?.value || '').replace(/\D+/g, '')) || 0;
    if (orderId <= 0) {
      showNotice(refillNoticeEl, 'err', 'Masukkan ID order yang valid.');
      return;
    }

    await requestRefill(orderId);
  });
}

if (refillBodyEl) {
  refillBodyEl.addEventListener('click', async (event) => {
    const checkBtn = event.target.closest('[data-check-refill]');
    if (!checkBtn) return;

    const refillId = Number(checkBtn.dataset.checkRefill || 0);
    if (!refillId) return;

    await checkRefillStatus(refillId);
  });
}

if (ordersPaginationEl) {
  ordersPaginationEl.addEventListener('click', (event) => {
    const button = event.target.closest('[data-page]');
    if (!button) return;

    const nextPage = Number(button.dataset.page || 1);
    if (!Number.isFinite(nextPage) || nextPage <= 0) return;

    state.history.page = nextPage;
    renderOrders();
  });
}

if (adminPaymentBodyEl) {
  adminPaymentBodyEl.addEventListener('click', async (event) => {
    const actionButton = event.target.closest('[data-admin-pay-action]');
    if (!actionButton) return;

    const action = actionButton.dataset.adminPayAction || '';
    const orderId = Number(actionButton.dataset.adminPayOrder || 0);
    if (!orderId || !action) return;

    const notePrompt = action === 'verify'
      ? 'Catatan admin (opsional):'
      : 'Alasan pembatalan (opsional):';
    const adminNote = window.prompt(notePrompt, '');
    if (adminNote === null) return;

    showNotice(adminPaymentNoticeEl, 'info', 'Memproses verifikasi pembayaran...');

    const { data } = await apiRequest('./api/order_admin_verify.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        order_id: orderId,
        action,
        admin_note: adminNote,
      }),
    });

    if (!data.status) {
      showNotice(adminPaymentNoticeEl, 'err', data?.data?.msg || 'Gagal memproses verifikasi.');
      return;
    }

    showNotice(adminPaymentNoticeEl, 'ok', data?.data?.msg || 'Verifikasi berhasil.');
    await Promise.all([
      fetchSession({ attempts: SESSION_FETCH_RETRY_ATTEMPTS, softFail: true }),
      loadOrders({ force: true }),
      loadTop5Services({ force: true }),
      loadAdminPaymentOrders({ force: true }),
      loadAdminOrderHistory({ force: true }),
    ]);
    updateHeaderStats();
  });
}

if (adminOrderHistoryStatusEl) {
  adminOrderHistoryStatusEl.addEventListener('change', async () => {
    state.adminOrderHistoryFilter = {
      ...state.adminOrderHistoryFilter,
      status: normalizeAdminOrderHistoryStatus(adminOrderHistoryStatusEl.value),
      page: 1,
    };
    await loadAdminOrderHistory({ force: true });
  });
}

if (adminOrderHistoryPerPageEl) {
  adminOrderHistoryPerPageEl.addEventListener('change', async () => {
    const perPage = Math.min(100, Math.max(10, Number(adminOrderHistoryPerPageEl.value || 25)));
    state.adminOrderHistoryFilter = {
      ...state.adminOrderHistoryFilter,
      perPage,
      page: 1,
    };
    await loadAdminOrderHistory({ force: true });
  });
}

if (adminOrderHistorySearchEl) {
  const debouncedAdminHistorySearch = debounce(async () => {
    state.adminOrderHistoryFilter = {
      ...state.adminOrderHistoryFilter,
      query: String(adminOrderHistorySearchEl.value || '').trim(),
      page: 1,
    };
    await loadAdminOrderHistory({ force: true });
  }, ADMIN_HISTORY_INPUT_DEBOUNCE_MS);

  adminOrderHistorySearchEl.addEventListener('input', () => {
    debouncedAdminHistorySearch();
  });
}

if (adminOrderHistoryRefreshBtnEl) {
  adminOrderHistoryRefreshBtnEl.addEventListener('click', async () => {
    if (adminOrderHistoryNoticeEl) {
      showNotice(adminOrderHistoryNoticeEl, 'info', 'Memuat ulang riwayat pembelian admin...');
    }
    await loadAdminOrderHistory({ force: true });
    if (adminOrderHistoryNoticeEl) {
      showNotice(adminOrderHistoryNoticeEl, 'ok', 'Riwayat pembelian berhasil diperbarui.');
    }
  });
}

if (adminOrderHistoryPaginationEl) {
  adminOrderHistoryPaginationEl.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-admin-history-page]');
    if (!button) return;

    const nextPage = Number(button.dataset.adminHistoryPage || 1);
    if (!Number.isFinite(nextPage) || nextPage <= 0) return;

    state.adminOrderHistoryFilter = {
      ...state.adminOrderHistoryFilter,
      page: nextPage,
    };
    await loadAdminOrderHistory({ force: true, silent: true });
  });
}

if (depositAdminBodyEl) {
  depositAdminBodyEl.addEventListener('click', async (event) => {
    const actionButton = event.target.closest('[data-deposit-action]');
    if (!actionButton) return;

    const action = String(actionButton.dataset.depositAction || '').trim().toLowerCase();
    const depositId = Number(actionButton.dataset.depositId || 0);
    if (depositId <= 0 || !['approve', 'reject'].includes(action)) {
      return;
    }

    const promptText = action === 'approve'
      ? 'Catatan admin untuk approve (opsional):'
      : 'Alasan penolakan deposit (opsional):';
    const adminNote = window.prompt(promptText, '');
    if (adminNote === null) return;

    showNotice(depositAdminNoticeEl, 'info', 'Memproses verifikasi deposit...');
    const { data } = await apiRequest('./api/deposit_admin_update.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        deposit_id: depositId,
        action,
        admin_note: adminNote,
      }),
    });

    if (!data.status) {
      showNotice(depositAdminNoticeEl, 'err', data?.data?.msg || 'Gagal memproses deposit.');
      return;
    }

    showNotice(depositAdminNoticeEl, 'ok', data?.data?.msg || 'Deposit berhasil diproses.');
    await Promise.all([
      fetchSession({ attempts: SESSION_FETCH_RETRY_ATTEMPTS, softFail: true }),
      loadDepositHistory({ force: true }),
      loadAdminDeposits({ force: true }),
    ]);
    updateHeaderStats();
  });
}

if (newsAdminBodyEl) {
  newsAdminBodyEl.addEventListener('click', async (event) => {
    const editButton = event.target.closest('[data-news-edit]');
    if (editButton) {
      const newsId = Number(editButton.dataset.newsEdit || 0);
      if (!newsId) return;

      const selected = state.adminNews.find((item) => Number(item.id) === newsId);
      if (!selected) return;

      fillNewsForm(selected);
      if (newsNoticeEl) hideNotice(newsNoticeEl);
      applyPanelView('dashboard');
      updateUrlForView('dashboard');
      return;
    }

    const deleteButton = event.target.closest('[data-news-delete]');
    if (!deleteButton) return;

    const newsId = Number(deleteButton.dataset.newsDelete || 0);
    if (!newsId) return;

    const confirmed = window.confirm(`Hapus berita #${newsId}?`);
    if (!confirmed) return;

    showNotice(newsNoticeEl, 'info', 'Menghapus berita...');

    const { data } = await apiRequest('./api/news_admin_delete.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: newsId }),
    });

    if (!data.status) {
      showNotice(newsNoticeEl, 'err', data?.data?.msg || 'Gagal menghapus berita.');
      return;
    }

    showNotice(newsNoticeEl, 'ok', data?.data?.msg || 'Berita berhasil dihapus.');
    await Promise.all([loadNews({ force: true }), loadAdminNews({ force: true })]);
  });
}

if (btnRefresh) {
  btnRefresh.addEventListener('click', async () => {
    try {
      await refreshDashboard();
    } catch (error) {
      showNotice(orderNotice, 'err', error.message || 'Gagal refresh dashboard.');
    }
  });
}

if (btnLogout) {
  btnLogout.addEventListener('click', async () => {
  await apiRequest('./api/auth_logout.php', { method: 'POST' });
  stopAdminPendingPoller();
  stopPendingPaymentSyncPoller();
  stopCsChatPoller();
  stopCsChatAdminPoller();
  if (serviceInfoRafId && typeof cancelAnimationFrame === 'function') {
    cancelAnimationFrame(serviceInfoRafId);
  }
  serviceInfoRafId = 0;
  apiGetCache.clear();
  apiInFlight.clear();
  state.user = null;
  state.stats = { total_orders: 0, total_spent: 0 };
  state.services = [];
  state.serviceDirectory = {
    categories: [],
    categoryEntries: [],
    byProviderCategoryId: new Map(),
  };
  state.serviceIndex = {
    categories: [],
    categoryEntries: [],
    byCategory: new Map(),
    byCategorySortedByPrice: new Map(),
    byServiceId: new Map(),
    byProviderCategoryId: new Map(),
    categoryProviderId: new Map(),
    globalSortedByPrice: [],
    byExactName: new Map(),
    byOptionLabel: new Map(),
  };
  state.selectedServiceId = 0;
  state.servicesLoaded = false;
  state.servicesLoadingPromise = null;
  state.servicesSearchCache = new Map();
  state.serviceSearchLast = {
    category: '',
    query: '',
    isIdQuery: false,
    results: [],
  };
  state.serviceOptionsRenderKey = '';
  state.lastResolvedCategory = '';
  state.servicesSearchRequestId = 0;
  state.topServices = [];
  state.topServicesLoaded = false;
  state.orders = [];
  state.ordersLoaded = false;
  state.ordersRequestId = 0;
  state.refills = [];
  state.refillsLoaded = false;
  state.payment = null;
  state.paymentMethods = parsePaymentMethodsFromPage();
  state.deposits = [];
  state.depositsLoaded = false;
  state.adminDeposits = [];
  state.adminDepositsLoaded = false;
  state.lastCheckout = null;
  state.adminPaymentOrders = [];
  state.adminPaymentOrdersLoaded = false;
  state.adminPaymentRequestId = 0;
  state.adminOrderHistory = [];
  state.adminOrderHistoryLoaded = false;
  state.adminOrderHistoryRequestId = 0;
  state.adminOrderHistoryFilter = {
    status: 'all',
    query: '',
    page: 1,
    perPage: 25,
    total: 0,
    totalPages: 1,
  };
  state.news = [];
  state.newsLoaded = false;
  state.newsMeta = {
    web_fetch_status: '',
    web_fetch_message: '',
  };
  state.adminNews = [];
  state.adminNewsLoaded = false;
  state.tickets = [];
  state.ticketsLoaded = false;
  state.ticketRequestId = 0;
  state.ticketDetail = null;
  state.ticketMessages = [];
  state.ticketFilter = {
    status: 'all',
    query: '',
  };
  resetCsChatState();
  state.adminCsChats = [];
  state.adminCsChatsLoaded = false;
  state.history = {
    status: 'ALL',
    idQuery: '',
    targetQuery: '',
    serviceQuery: '',
    perPage: 10,
    page: 1,
  };
  state.serviceCatalog = {
    query: '',
    category: '',
    sortBy: 'category_name',
    sortDir: 'asc',
    perPage: 50,
    page: 1,
  };
  state.serviceCatalogRows = [];
  state.serviceCatalogTotal = 0;
  state.serviceCatalogTotalPages = 1;
  state.serviceCatalogLoaded = false;
  state.serviceCatalogRequestId = 0;
  state.gameServices = [];
  state.gameServicesLoaded = false;
  state.gameServicesRequestId = 0;
  state.gameServiceSearchCache = new Map();
  state.gameSelectedService = null;
  state.gameOrders = [];
  state.gameOrdersLoaded = false;
  state.gameOrdersRequestId = 0;
  state.gameAdminPendingOrders = [];
  state.gameAdminPendingLoaded = false;
  state.gameAdminPendingRequestId = 0;
  state.gameHistoryFilter = {
    status: 'ALL',
    idQuery: '',
    serviceQuery: '',
  };
  state.panelHighlights = [];
  state.panelHighlightsLoaded = false;
  state.panelInfoMeta = {
    total_services: 0,
    total_categories: 0,
    synced_at: '',
  };
  state.panelInfoClosed = false;
  if (serviceCatalogSearchEl) serviceCatalogSearchEl.value = '';
  if (serviceCatalogCategoryEl) serviceCatalogCategoryEl.value = '';
  if (serviceCatalogSortByEl) serviceCatalogSortByEl.value = 'category_name';
  if (serviceCatalogSortDirEl) serviceCatalogSortDirEl.value = 'asc';
  if (servicesCatalogPerPageEl) servicesCatalogPerPageEl.value = '50';
  if (ticketStatusFilterEl) ticketStatusFilterEl.value = 'all';
  if (ticketSearchInputEl) ticketSearchInputEl.value = '';
  if (adminOrderHistorySearchEl) adminOrderHistorySearchEl.value = '';
  if (adminOrderHistoryStatusEl) adminOrderHistoryStatusEl.value = 'all';
  if (adminOrderHistoryPerPageEl) adminOrderHistoryPerPageEl.value = '25';
  if (ticketSubjectEl) ticketSubjectEl.value = '';
  if (ticketOrderIdEl) ticketOrderIdEl.value = '';
  if (ticketMessageEl) ticketMessageEl.value = '';
  if (ticketPriorityEl) ticketPriorityEl.value = 'normal';
  if (ticketCategoryEl) ticketCategoryEl.value = 'Laporan Order';
  if (gameCategorySelectEl) gameCategorySelectEl.value = '';
  if (gameServiceInputEl) gameServiceInputEl.value = '';
  if (gameTargetInputEl) gameTargetInputEl.value = '';
  if (gameContactInputEl) gameContactInputEl.value = '';
  if (gameHistoryStatusEl) gameHistoryStatusEl.value = 'ALL';
  if (gameHistoryIdSearchEl) gameHistoryIdSearchEl.value = '';
  if (gameHistoryServiceSearchEl) gameHistoryServiceSearchEl.value = '';
  closePaymentQrModal();
  closeNewsModal();
  closeAccountMenu();
  hideAllSuggestions(true);
  resetNewsForm();
  clearTicketDetail();
  setCsChatPanelOpen(false);
  if (csChatInputEl) csChatInputEl.value = '';
  state.currentView = 'utama';
  updateUrlForView('utama');
  hideCheckoutPanel();
  renderTop5Services();
  renderRefills();
  renderServicesCatalog();
  renderGameOrders();
  renderGameAdminPendingOrders();
  renderDashboardHighlights();
  renderTickets();
  renderAdminPaymentOrders();
  renderAdminOrderHistory();
  renderCsChat();
  renderAdminCsChats();
  renderNews();
  renderAdminNews();
  renderPanelInfoTicker();
  updateShareSectionState();
  setPanelInfoClosed(false);
  updateHeaderStats();
  updateAdminMenuLabel();
  updateAccountMenu();
  updateProfilePanel();
  if (shareNoticeEl) hideNotice(shareNoticeEl);
  setViewLoggedIn(false);
  switchAuthTab('login');
  showNotice(authNotice, 'ok', 'Kamu sudah logout.');
  });
}

function hideLogoIfMissing(imgId) {
  const image = document.getElementById(imgId);
  if (!image) return;

  image.addEventListener('error', () => {
    image.style.display = 'none';
  });
}

hideLogoIfMissing('authLogo');
hideLogoIfMissing('sideLogo');

(async function initApp() {
  if (pageEl?.dataset?.build && typeof console !== 'undefined' && typeof console.info === 'function') {
    console.info(`[Odyssiavault] build ${pageEl.dataset.build}`);
  }
  switchAuthTab('login');
  hideAllSuggestions(true);
  sideTipsEls.forEach((el) => {
    if (!el) return;
    el.classList.add('hidden');
    if (typeof el.remove === 'function') {
      el.remove();
    }
  });
  panelNavLinks.forEach((link) => {
    if (String(link.dataset.view || '') === 'profile') {
      link.classList.add('hidden');
    }
  });
  state.paymentMethods = parsePaymentMethodsFromPage();
  state.ticketFilter.status = String(ticketStatusFilterEl?.value || 'all').toLowerCase();
  state.ticketFilter.query = String(ticketSearchInputEl?.value || '');
  hideCheckoutPanel();
  clearTicketDetail();
  renderTop5Services();
  renderRefills();
  renderServicesCatalog();
  renderGameOrders();
  renderGameAdminPendingOrders();
  renderDashboardHighlights();
  renderTickets();
  renderAdminPaymentOrders();
  renderAdminOrderHistory();
  renderCsChat();
  renderAdminCsChats();
  renderNews();
  renderAdminNews();
  renderPanelInfoTicker();
  updateShareSectionState();
  updateAdminMenuLabel();
  updateCsChatWidgetVisibility();
  restorePanelInfoState();
  updateAccountMenu();
  updateProfilePanel();

  try {
    const loggedIn = await fetchSession({
      attempts: SESSION_FETCH_RETRY_ATTEMPTS,
      softFail: false,
    });
    if (loggedIn) {
      const initialView = normalizePanelView(pageEl?.dataset?.initialView || 'utama');
      applyPanelView(initialView);
      updateUrlForView(initialView);
      await ensureViewData(initialView, { force: false });
    }
  } catch (error) {
    setViewLoggedIn(false);
    showNotice(authNotice, 'err', error.message || 'Gagal inisialisasi aplikasi.');
  }
})();


