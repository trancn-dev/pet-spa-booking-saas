// JSON calls from drawers (S2, S3). Laravel sets the XSRF-TOKEN cookie; we send it back.

export interface ApiResult<T = unknown> {
    ok: boolean;
    status: number;
    data: T;
}

const xsrf = (): string => {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
};

export async function api<T = any>(method: 'GET' | 'POST', url: string, body?: unknown): Promise<ApiResult<T>> {
    const res = await fetch(url, {
        method,
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(method === 'POST' ? { 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrf() } : {}),
        },
        body: method === 'POST' ? JSON.stringify(body ?? {}) : undefined,
    });
    let data: any = null;
    try {
        data = await res.json();
    } catch {
        data = null;
    }
    return { ok: res.ok, status: res.status, data };
}

/** First validation message of a 422, or the response message. */
export const errorMessage = (r: ApiResult<any>): string =>
    (r.data?.errors && (Object.values(r.data.errors)[0] as string[])?.[0]) || r.data?.message || 'Có lỗi, vui lòng thử lại.';
