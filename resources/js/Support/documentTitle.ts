const APP_NAME = 'DORMIDA WORK';

export const documentTitle = (pageTitle: string): string =>
    pageTitle.trim() ? `${pageTitle.trim()} - ${APP_NAME}` : APP_NAME;
