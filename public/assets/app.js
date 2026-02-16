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
  },
  selectedServiceId: 0,
  servicesLoaded: false,
  servicesLoadingPromise: null,
  servicesSearchCache: new Map(),
  servicesSearchRequestId: 0,
  topServices: [],
  topServicesLoaded: false,
  orders: [],
  ordersLoaded: false,
  refills: [],
  refillsLoaded: false,
  paymentMethods: [],
  lastCheckout: null,
  adminPaymentOrders: [],
  adminPaymentOrdersLoaded: false,
  news: [],
  newsLoaded: false,
  adminNews: [],
  adminNewsLoaded: false,
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
  hasTop5Data: false,
  currentView: 'dashboard',
};

const NEWS_SOURCE_BRAND = 'Odyssiavault';
const MAX_CATEGORY_OPTIONS = 200;
const MAX_SERVICE_OPTIONS = 300;
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

const pageEl = document.querySelector('.page');
const PANEL_VIEWS = new Set(['dashboard', 'profile', 'top5', 'purchase', 'refill', 'deposit', 'ticket', 'services', 'pages', 'admin']);
const LEGACY_VIEW_MAP = {
  dashboardsection: 'dashboard',
  profilesection: 'profile',
  top5section: 'top5',
  ordersection: 'purchase',
  historysection: 'purchase',
  refillsection: 'refill',
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
const serviceInputEl = document.getElementById('serviceInput');
const serviceOptionsEl = document.getElementById('serviceOptions');
const targetEl = document.getElementById('target');
const quantityEl = document.getElementById('quantity');
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
const pricePer1000El = document.getElementById('pricePer1000');
const top5SectionEl = document.getElementById('top5Section');
const top5ListEl = document.getElementById('top5List');
const top5EmptyStateEl = document.getElementById('top5EmptyState');
const emergencyServiceTextEl = document.getElementById('emergencyServiceText');

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
const paymentQrToHistoryEl = document.getElementById('paymentQrToHistory');
const historySectionEl = document.getElementById('historySection');

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
      return;
    }
    byExactName.set(key, service);
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
  return PANEL_VIEWS.has(mapped) ? mapped : 'dashboard';
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

function showNotice(el, type, message) {
  el.className = `notice ${type}`;
  el.textContent = message;
  el.classList.remove('hidden');
}

function hideNotice(el) {
  el.classList.add('hidden');
  el.textContent = '';
}

function syncLoginPasswordToggleState() {
  if (!loginPasswordEl || !loginPasswordToggleEl) return;

  const isVisible = loginPasswordEl.type === 'text';
  loginPasswordToggleEl.textContent = isVisible ? 'Sembunyikan' : 'Lihat';
  loginPasswordToggleEl.setAttribute('aria-pressed', isVisible ? 'true' : 'false');
  loginPasswordToggleEl.setAttribute('aria-label', isVisible ? 'Sembunyikan password' : 'Lihat password');
}

async function apiRequest(url, options = {}) {
  const mergedHeaders = {
    Accept: 'application/json',
    ...(options.headers || {}),
  };
  const requestOptions = {
    credentials: 'same-origin',
    ...options,
    headers: mergedHeaders,
  };
  let response;
  try {
    response = await fetch(url, requestOptions);
  } catch {
    return {
      response: null,
      data: { status: false, data: { msg: 'Tidak dapat terhubung ke server.' } },
    };
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

  if (!data || typeof data !== 'object') {
    const contentType = String(response.headers.get('content-type') || '').toLowerCase();
    const looksHtml = contentType.includes('text/html') || rawBody.trim().startsWith('<!doctype') || rawBody.trim().startsWith('<html');
    data = {
      status: false,
      data: {
        msg: looksHtml
          ? `Respon server bukan JSON (HTTP ${statusCode || '-'}).`
          : `Respon JSON server tidak valid (HTTP ${statusCode || '-'}).`,
      },
    };
  }

  return { response, data };
}

function switchAuthTab(tab) {
  const loginMode = tab === 'login';
  tabLogin.classList.toggle('active', loginMode);
  tabRegister.classList.toggle('active', !loginMode);
  loginForm.classList.toggle('hidden', !loginMode);
  registerForm.classList.toggle('hidden', loginMode);
  hideNotice(authNotice);
}

function setViewLoggedIn(isLoggedIn) {
  authView.classList.toggle('hidden', isLoggedIn);
  appView.classList.toggle('hidden', !isLoggedIn);
}

function applyPanelView(view) {
  const isAdmin = String(state.user?.role || '') === 'admin';
  const requestedView = normalizePanelView(view);
  const resolvedView = (!isAdmin && requestedView === 'admin') ? 'dashboard' : requestedView;

  if (requestedView !== resolvedView) {
    updateUrlForView(resolvedView);
  }

  state.currentView = resolvedView;

  if (adminMenuLinkEl) {
    adminMenuLinkEl.classList.toggle('hidden', !isAdmin);
  }

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

  if (top5SectionEl && top5EmptyStateEl) {
    if (state.currentView !== 'top5') {
      top5SectionEl.classList.add('hidden');
      top5EmptyStateEl.classList.add('hidden');
    } else if (state.hasTop5Data) {
      top5SectionEl.classList.remove('hidden');
      top5EmptyStateEl.classList.add('hidden');
    } else {
      top5SectionEl.classList.add('hidden');
      top5EmptyStateEl.classList.remove('hidden');
    }
  }

  if (!isAdmin && newsAdminSectionEl) {
    newsAdminSectionEl.classList.add('hidden');
  }
}

function updateHeaderStats() {
  if (!state.user) return;

  const displayName = state.user.username || 'buyer';
  if (welcomeText) {
    welcomeText.textContent = `Halo @${displayName}. Pilih layanan, checkout, bayar langsung, lalu konfirmasi pembayaran.`;
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

async function fetchSession() {
  const { data } = await apiRequest('./api/auth_me.php');
  if (!data.status) {
    state.user = null;
    updateProfilePanel();
    setViewLoggedIn(false);
    return false;
  }

  state.user = data.data.user;
  state.stats = data.data.stats || { total_orders: 0, total_spent: 0 };
  updateHeaderStats();
  updateProfilePanel();
  setViewLoggedIn(true);
  return true;
}

function selectedService() {
  const byServiceId = state.serviceIndex?.byServiceId instanceof Map
    ? state.serviceIndex.byServiceId
    : new Map();

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

  for (const service of byServiceId.values()) {
    const label = normalizeQuery(serviceOptionLabel(service));
    if (label === normalizedInput) {
      state.selectedServiceId = Number(service.id || 0);
      return service;
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
    applyPanelView(state.currentView || 'dashboard');
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

  applyPanelView(state.currentView || 'dashboard');
}

function updateCommentVisibility(service) {
  if (!service) {
    commentGroupEl.classList.add('hidden');
    mentionGroupEl.classList.add('hidden');
    advancedFieldsEl.classList.add('hidden');
    komenEl.required = false;
    usernamesEl.required = false;
    return;
  }

  const commentRequired = isCommentService(service);
  const mentionRequired = isMentionsCustomListService(service);

  if (commentRequired) {
    commentGroupEl.classList.remove('hidden');
    komenEl.required = true;
    commentHintEl.textContent = 'Layanan ini bertipe Komen. Komentar wajib diisi sebelum checkout.';
  } else {
    commentGroupEl.classList.add('hidden');
    komenEl.required = false;
    komenEl.value = '';
  }

  if (mentionRequired) {
    mentionGroupEl.classList.remove('hidden');
    usernamesEl.required = true;
    mentionHintEl.textContent = 'Layanan ini bertipe Mentions Custom List. Isi usernames agar order dapat diproses.';
  } else {
    mentionGroupEl.classList.add('hidden');
    usernamesEl.required = false;
    usernamesEl.value = '';
  }

  const showAdvanced = commentRequired || mentionRequired;
  advancedFieldsEl.classList.toggle('hidden', !showAdvanced);
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
  if (!checkoutPanelEl || !checkoutSummaryEl || !checkoutMethodsEl || !paymentMethodSelectEl) return;

  const methods = Array.isArray(orderData?.payment_methods) && orderData.payment_methods.length
    ? orderData.payment_methods
    : state.paymentMethods;
  state.paymentMethods = methods;
  state.lastCheckout = {
    order_id: Number(orderData?.order_id || 0),
    payment_deadline_at: orderData?.payment_deadline_at || '',
    total_sell_price: Number(orderData?.total_sell_price || 0),
  };

  const summaryLines = [
    `Order ID: #${orderData?.order_id || '-'}`,
    `Layanan: ${orderData?.service?.name || '-'}`,
    `Target: ${orderData?.target || '-'}`,
    `Jumlah: ${formatInteger(orderData?.quantity || 0)}`,
    `Total Pembayaran: ${rupiah(orderData?.total_sell_price || 0)}`,
    `Batas Pembayaran: ${formatDateTime(orderData?.payment_deadline_at || '')}`,
  ];
  checkoutSummaryEl.textContent = summaryLines.join('\n');

  checkoutMethodsEl.innerHTML = methods.map((method) => `
    <div class="contact-link">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M19 5H5a2 2 0 0 0-2 2v13l4-3h12a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2Zm-7 9H7v-2h5v2Zm5-4H7V8h10v2Z"></path></svg>
      <div>
        <strong>${escapeHtml(method.name)}</strong>
        <span>${escapeHtml(method.account_number)} a.n. ${escapeHtml(method.account_name || '-')}</span>
      </div>
    </div>
  `).join('');

  paymentMethodSelectEl.innerHTML = methods.map((method) => (
    `<option value="${escapeHtml(method.code)}">${escapeHtml(method.name)} - ${escapeHtml(method.account_number)}</option>`
  )).join('');

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
  }).slice(0, MAX_CATEGORY_OPTIONS);

  categoryOptionsEl.innerHTML = limitedCategories
    .map((category) => `<option value="${escapeHtml(String(category))}"></option>`)
    .join('');

  fillServiceOptions();
}

async function fillServiceOptions(options = {}) {
  if (!serviceInputEl || !serviceOptionsEl) return;
  const force = !!options.force;

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
  const serviceQuery = serviceIdMatch
    ? String(serviceIdMatch[1] || '').trim()
    : normalizeQuery(rawServiceInput);
  if (!category && !serviceQuery) {
    state.services = [];
    buildServiceIndex();
    serviceOptionsEl.innerHTML = '';
    updateServiceInfo();
    return;
  }

  const cacheKey = `${category || '__all__'}::${serviceQuery}`;
  let nextServices = (!force && state.servicesSearchCache.has(cacheKey))
    ? (state.servicesSearchCache.get(cacheKey) || [])
    : null;

  if (!Array.isArray(nextServices)) {
    const requestId = ++state.servicesSearchRequestId;
    const params = new URLSearchParams({
      mode: 'search',
      variant: 'services',
      q: serviceQuery,
      limit: String(MAX_SERVICE_OPTIONS),
    });
    if (category) {
      params.set('category', category);
    }
    const { data } = await apiRequest(`./api/services.php?${params.toString()}`);
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
      updateServiceInfo();
      return;
    }

    nextServices = Array.isArray(data.data?.services) ? data.data.services : [];
    state.servicesSearchCache.set(cacheKey, nextServices);
  }

  state.services = [...nextServices];
  buildServiceIndex();
  const pool = Array.isArray(state.services) ? state.services : [];
  const limitedServices = pool.slice(0, MAX_SERVICE_OPTIONS);

  serviceOptionsEl.innerHTML = limitedServices
    .map((service) => `<option value="${escapeHtml(serviceOptionLabel(service))}"></option>`)
    .join('');

  const resolvedService = selectedService();
  if (resolvedService) {
    serviceInputEl.value = serviceOptionLabel(resolvedService);
  }

  updateServiceInfo();
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

  const qty = Number((quantityEl.value || '0').replace(/\D+/g, '')) || 0;
  const sellPricePer1000 = Number(service.sell_price_per_1000 ?? service.sell_price ?? 0);
  const sellUnitPrice = Number(service.sell_unit_price ?? (sellPricePer1000 / 1000));
  const estimate = qty > 0 ? Math.ceil((sellPricePer1000 * qty) / 1000) : 0;

  if (pricePer1000El) {
    pricePer1000El.value = rupiah(sellPricePer1000);
  }

  serviceInfoEl.textContent = [
    `[${service.category || 'Lainnya'}] ${service.name}`,
    `Harga Jual / 1000: ${rupiah(sellPricePer1000)}`,
    `Harga Satuan: ${rupiahUnit(sellUnitPrice)} / item`,
    `Min/Max: ${service.min} - ${service.max}`,
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
      ? `./api/services.php?mode=categories&variant=services&_t=${Date.now()}`
      : './api/services.php?mode=categories&variant=services';
    const { data } = await apiRequest(endpoint);

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
      };
      state.selectedServiceId = 0;
      state.servicesSearchCache = new Map();
      state.serviceCatalogRows = [];
      state.serviceCatalogTotal = 0;
      state.serviceCatalogTotalPages = 1;
      state.serviceCatalogLoaded = false;
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
      return false;
    }

    if (force) {
      state.servicesSearchCache = new Map();
      state.selectedServiceId = 0;
      state.serviceCatalogLoaded = false;
    }

    const categories = Array.isArray(data.data?.categories) ? data.data.categories : [];
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
    await fillServiceOptions({ force });
    if (serviceInfoEl && !selectedService()) {
      serviceInfoEl.textContent = 'Pilih kategori lalu pilih layanan untuk melihat detail harga dan ketentuan.';
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
    variant: 'services',
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
  const { data } = await apiRequest(endpoint);

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
  const isAdmin = String(state.user?.role || '') === 'admin';
  if (!isAdmin) {
    state.adminPaymentOrders = [];
    state.adminPaymentOrdersLoaded = false;
    renderAdminPaymentOrders();
    return;
  }

  if (!force && state.adminPaymentOrdersLoaded) {
    renderAdminPaymentOrders();
    return;
  }

  const { data } = await apiRequest('./api/order_admin_payments.php?status=waiting&limit=100');
  if (!data.status) {
    state.adminPaymentOrders = [];
    state.adminPaymentOrdersLoaded = false;
    renderAdminPaymentOrders();
    if (adminPaymentNoticeEl) {
      showNotice(adminPaymentNoticeEl, 'err', data?.data?.msg || 'Gagal memuat verifikasi pembayaran.');
    }
    return;
  }

  state.adminPaymentOrders = Array.isArray(data.data?.orders) ? data.data.orders : [];
  state.adminPaymentOrdersLoaded = true;
  renderAdminPaymentOrders();
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
    historySectionEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
}

function openPaymentQrModal(orderData) {
  if (!paymentQrModalEl || !paymentQrSummaryEl || !paymentQrImageEl) {
    focusOrderHistory(orderData?.order_id);
    return;
  }

  const orderId = Number(orderData?.order_id || 0);
  const total = Number(orderData?.total_sell_price || 0);
  const deadline = formatDateTime(orderData?.payment_deadline_at || '');
  const qrisPath = resolveAssetPath(pageEl?.dataset?.qrisPath || 'assets/qris.png');

  if (paymentQrTitleEl) {
    paymentQrTitleEl.textContent = `Pembayaran Order #${orderId || '-'}`;
  }

  paymentQrSummaryEl.textContent = [
    `Nominal Bayar: ${rupiah(total)}`,
    `Batas Pembayaran: ${deadline}`,
  ].join('\n');

  paymentQrImageEl.src = qrisPath;

  if (paymentQrInstructionEl) {
    paymentQrInstructionEl.textContent = [
      '1. Scan QR di atas dan bayar sesuai nominal.',
      '2. Setelah transfer, order akan menunggu konfirmasi admin.',
      '3. Pantau status pembayaran pada menu Riwayat Pesanan.',
    ].join('\n');
  }

  paymentQrModalEl.classList.remove('hidden');
  focusOrderHistory(orderId);
}

function renderNews() {
  if (!newsListEl) return;

  if (!state.news.length) {
    newsListEl.innerHTML = '<div class="box">Belum ada berita terbaru saat ini.</div>';
    return;
  }

  newsListEl.innerHTML = state.news.map((news) => `
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
    renderNews();
    return;
  }

  state.news = Array.isArray(data.data?.news) ? data.data.news : [];
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

async function loadDepositHistory() {
  const { data } = await apiRequest('./api/deposit_history.php?limit=50');

  if (!data.status) {
    state.deposits = [];
    renderDepositHistory();
    if (depositNoticeEl) {
      showNotice(depositNoticeEl, 'err', data?.data?.msg || 'Gagal memuat riwayat deposit.');
    }
    return;
  }

  state.payment = data.data?.payment || state.payment;
  state.deposits = Array.isArray(data.data?.deposits) ? data.data.deposits : [];

  renderPaymentInfo();
  renderDepositHistory();
}

async function loadAdminDeposits() {
  const isAdmin = String(state.user?.role || '') === 'admin';
  if (!isAdmin) {
    state.adminDeposits = [];
    renderAdminDeposits();
    return;
  }

  const { data } = await apiRequest('./api/deposit_admin_list.php?status=pending&limit=100');
  if (!data.status) {
    state.adminDeposits = [];
    renderAdminDeposits();
    if (depositAdminNoticeEl) {
      showNotice(depositAdminNoticeEl, 'err', data?.data?.msg || 'Gagal memuat data verifikasi deposit.');
    }
    return;
  }

  state.adminDeposits = Array.isArray(data.data?.deposits) ? data.data.deposits : [];
  renderAdminDeposits();
}

async function loadOrders(options = {}) {
  const force = !!options.force;
  if (!force && state.ordersLoaded) {
    renderOrders();
    return;
  }

  const { data } = await apiRequest('./api/orders.php?limit=200');
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

async function checkOrderStatus(orderId) {
  showNotice(ordersNotice, 'info', `Mengecek status order #${orderId}...`);
  const { data } = await apiRequest('./api/order_status.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ order_id: orderId }),
  });

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
    `Refill berhasil diajukan.\nRefill ID: #${refillInfo.refill_id || '-'}\nProvider Refill ID: ${refillInfo.provider_refill_id || '-'}\nStatus: ${refillInfo.status || 'Diproses'}`
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

async function ensureViewData(view, options = {}) {
  const force = !!options.force;
  const normalizedView = normalizePanelView(view);
  const isAdmin = String(state.user?.role || '') === 'admin';

  switch (normalizedView) {
    case 'dashboard':
      await loadNews({ force });
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
        loadTop5Services({ force }),
      ]);
      break;
    case 'refill':
      await loadRefills({ force });
      break;
    case 'services':
      await loadServices({ force });
      await loadServicesCatalog({ force });
      break;
    case 'admin':
      if (isAdmin) {
        await Promise.all([
          loadAdminPaymentOrders({ force }),
          loadAdminNews({ force }),
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
  if (newsNoticeEl) hideNotice(newsNoticeEl);
  const loggedIn = await fetchSession();
  if (!loggedIn) return;

  await ensureViewData(state.currentView || 'dashboard', { force: true });

  applyPanelView(state.currentView || 'dashboard');
}

tabLogin.addEventListener('click', () => switchAuthTab('login'));
tabRegister.addEventListener('click', () => switchAuthTab('register'));

panelNavLinks.forEach((link) => {
  link.addEventListener('click', async (event) => {
    const nextView = normalizePanelView(link.dataset.view || '');
    event.preventDefault();
    applyPanelView(nextView);
    updateUrlForView(nextView);
    if (state.user) {
      await ensureViewData(nextView, { force: false });
    }
  });
});

loginForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  showNotice(authNotice, 'info', 'Memproses login...');

  const payload = {
    identity: document.getElementById('loginIdentity').value.trim(),
    password: document.getElementById('loginPassword').value,
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

  showNotice(authNotice, 'ok', 'Login berhasil. Memuat dashboard...');
  window.location.assign('./?page=dashboard');
});

registerForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  showNotice(authNotice, 'info', 'Membuat akun...');

  const username = document.getElementById('regUsername').value.trim();
  const password = document.getElementById('regPassword').value;

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

  showNotice(authNotice, 'ok', 'Registrasi berhasil. Memuat dashboard...');
  window.location.assign('./?page=dashboard');
});

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

document.getElementById('orderForm').addEventListener('submit', async (event) => {
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
      const { data: detailData } = await apiRequest(`./api/services.php?mode=detail&variant=services&id=${fallbackId}`);
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

  await Promise.all([fetchSession(), loadOrders({ force: true }), loadAdminPaymentOrders({ force: true })]);
  updateHeaderStats();
  openPaymentQrModal(info);
});

if (paymentConfirmBtnEl) {
  paymentConfirmBtnEl.addEventListener('click', async () => {
    const checkout = state.lastCheckout;
    if (!checkout?.order_id) {
      showNotice(paymentConfirmNoticeEl, 'err', 'Tidak ada checkout aktif untuk dikonfirmasi.');
      return;
    }

    const payload = {
      order_id: checkout.order_id,
      method_code: paymentMethodSelectEl?.value || '',
      payer_name: paymentPayerNameEl?.value?.trim() || '',
      payment_reference: paymentReferenceEl?.value?.trim() || '',
      payment_note: paymentPayerNoteEl?.value?.trim() || '',
    };

    if (!payload.method_code) {
      showNotice(paymentConfirmNoticeEl, 'err', 'Pilih metode pembayaran terlebih dahulu.');
      return;
    }

    showNotice(paymentConfirmNoticeEl, 'info', 'Mengirim konfirmasi pembayaran...');

    const { data } = await apiRequest('./api/order_payment_confirm.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload),
    });

    if (!data.status) {
      showNotice(paymentConfirmNoticeEl, 'err', data?.data?.msg || 'Konfirmasi pembayaran gagal.');
      return;
    }

    showNotice(paymentConfirmNoticeEl, 'ok', data?.data?.msg || 'Konfirmasi pembayaran berhasil.');
    await Promise.all([fetchSession(), loadOrders({ force: true }), loadAdminPaymentOrders({ force: true })]);
    updateHeaderStats();
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
    focusOrderHistory(state.lastCheckout?.order_id || 0);
  });
}

document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape') {
    closeNewsModal();
    closePaymentQrModal();
  }
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

if (categoryInputEl) {
  const debouncedFillCategoryOptions = debounce(fillCategoryOptions, 120);
  categoryInputEl.addEventListener('input', debouncedFillCategoryOptions);
  categoryInputEl.addEventListener('change', fillCategoryOptions);
}

if (serviceInputEl) {
  const debouncedFillServiceOptions = debounce(() => {
    fillServiceOptions();
  }, 240);
  serviceInputEl.addEventListener('input', debouncedFillServiceOptions);
  serviceInputEl.addEventListener('change', fillServiceOptions);
}

if (quantityEl) {
  quantityEl.addEventListener('input', updateServiceInfo);
}

if (serviceCatalogSearchEl) {
  const debouncedCatalogSearch = debounce(() => {
    state.serviceCatalog.query = serviceCatalogSearchEl.value || '';
    state.serviceCatalog.page = 1;
    loadServicesCatalog({ force: true });
  }, 260);
  serviceCatalogSearchEl.addEventListener('input', () => {
    debouncedCatalogSearch();
  });
}

if (serviceCatalogCategoryEl) {
  serviceCatalogCategoryEl.addEventListener('change', () => {
    state.serviceCatalog.category = serviceCatalogCategoryEl.value || '';
    state.serviceCatalog.page = 1;
    loadServicesCatalog({ force: true });
  });
}

if (serviceCatalogSortByEl) {
  serviceCatalogSortByEl.addEventListener('change', () => {
    state.serviceCatalog.sortBy = serviceCatalogSortByEl.value || 'category_name';
    state.serviceCatalog.page = 1;
    loadServicesCatalog({ force: true });
  });
}

if (serviceCatalogSortDirEl) {
  serviceCatalogSortDirEl.addEventListener('change', () => {
    state.serviceCatalog.sortDir = serviceCatalogSortDirEl.value === 'desc' ? 'desc' : 'asc';
    state.serviceCatalog.page = 1;
    loadServicesCatalog({ force: true });
  });
}

if (servicesCatalogPerPageEl) {
  servicesCatalogPerPageEl.addEventListener('change', () => {
    state.serviceCatalog.perPage = Number(servicesCatalogPerPageEl.value || 50);
    state.serviceCatalog.page = 1;
    loadServicesCatalog({ force: true });
  });
}

if (servicesCatalogPaginationEl) {
  servicesCatalogPaginationEl.addEventListener('click', (event) => {
    const button = event.target.closest('[data-services-page]');
    if (!button) return;

    const nextPage = Number(button.dataset.servicesPage || 1);
    if (!Number.isFinite(nextPage) || nextPage <= 0) return;

    state.serviceCatalog.page = nextPage;
    loadServicesCatalog({ force: true });
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
  historyOrderIdSearchEl.addEventListener('input', () => {
    state.history.idQuery = historyOrderIdSearchEl.value || '';
    state.history.page = 1;
    renderOrders();
  });
}

if (historyTargetSearchEl) {
  historyTargetSearchEl.addEventListener('input', () => {
    state.history.targetQuery = historyTargetSearchEl.value || '';
    state.history.page = 1;
    renderOrders();
  });
}

if (historyServiceSearchEl) {
  historyServiceSearchEl.addEventListener('input', () => {
    state.history.serviceQuery = historyServiceSearchEl.value || '';
    state.history.page = 1;
    renderOrders();
  });
}

if (historyPerPageEl) {
  historyPerPageEl.addEventListener('change', () => {
    state.history.perPage = Number(historyPerPageEl.value || 10);
    state.history.page = 1;
    renderOrders();
  });
}

ordersBody.addEventListener('click', async (event) => {
  const target = event.target.closest('[data-check-order]');
  if (!target) return;

  const orderId = Number(target.dataset.checkOrder || 0);
  if (!orderId) return;

  await checkOrderStatus(orderId);
});

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
      fetchSession(),
      loadOrders({ force: true }),
      loadTop5Services({ force: true }),
      loadAdminPaymentOrders({ force: true }),
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

btnRefresh.addEventListener('click', async () => {
  try {
    await refreshDashboard();
  } catch (error) {
    showNotice(orderNotice, 'err', error.message || 'Gagal refresh dashboard.');
  }
});

btnLogout.addEventListener('click', async () => {
  await apiRequest('./api/auth_logout.php', { method: 'POST' });
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
  };
  state.selectedServiceId = 0;
  state.servicesLoaded = false;
  state.servicesLoadingPromise = null;
  state.servicesSearchCache = new Map();
  state.servicesSearchRequestId = 0;
  state.topServices = [];
  state.topServicesLoaded = false;
  state.orders = [];
  state.ordersLoaded = false;
  state.refills = [];
  state.refillsLoaded = false;
  state.paymentMethods = parsePaymentMethodsFromPage();
  state.lastCheckout = null;
  state.adminPaymentOrders = [];
  state.adminPaymentOrdersLoaded = false;
  state.news = [];
  state.newsLoaded = false;
  state.adminNews = [];
  state.adminNewsLoaded = false;
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
  if (serviceCatalogSearchEl) serviceCatalogSearchEl.value = '';
  if (serviceCatalogCategoryEl) serviceCatalogCategoryEl.value = '';
  if (serviceCatalogSortByEl) serviceCatalogSortByEl.value = 'category_name';
  if (serviceCatalogSortDirEl) serviceCatalogSortDirEl.value = 'asc';
  if (servicesCatalogPerPageEl) servicesCatalogPerPageEl.value = '50';
  closePaymentQrModal();
  closeNewsModal();
  resetNewsForm();
  state.currentView = 'dashboard';
  updateUrlForView('dashboard');
  hideCheckoutPanel();
  renderTop5Services();
  renderRefills();
  renderServicesCatalog();
  renderAdminPaymentOrders();
  renderNews();
  renderAdminNews();
  updateHeaderStats();
  updateProfilePanel();
  setViewLoggedIn(false);
  switchAuthTab('login');
  showNotice(authNotice, 'ok', 'Kamu sudah logout.');
});

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
  switchAuthTab('login');
  state.paymentMethods = parsePaymentMethodsFromPage();
  hideCheckoutPanel();
  renderTop5Services();
  renderRefills();
  renderServicesCatalog();
  renderAdminPaymentOrders();
  renderNews();
  renderAdminNews();
  updateProfilePanel();

  try {
    const loggedIn = await fetchSession();
    if (loggedIn) {
      const initialView = normalizePanelView(pageEl?.dataset?.initialView || '');
      applyPanelView(initialView);
      updateUrlForView(initialView);
      await ensureViewData(initialView, { force: false });
    }
  } catch (error) {
    setViewLoggedIn(false);
    showNotice(authNotice, 'err', error.message || 'Gagal inisialisasi aplikasi.');
  }
})();


