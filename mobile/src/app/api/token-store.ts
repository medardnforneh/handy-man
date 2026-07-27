/**
 * The in-memory access token the API client attaches as a Bearer (P1-03). Kept as a tiny standalone
 * module so the client middleware can read it — and ask for a refresh — without depending on Angular
 * DI (which would create a cycle: AuthService → client → AuthService). AuthService is the only writer:
 * it sets the short-lived access token after an OTP verify / refresh, registers the refresh handler,
 * and clears everything on logout. Refresh tokens never live here (they stay inside AuthService).
 */
type RefreshHandler = () => Promise<string | null>;

let accessToken: string | null = null;
let refreshHandler: RefreshHandler | null = null;

export const tokenStore = {
  get(): string | null {
    return accessToken;
  },
  set(token: string | null): void {
    accessToken = token;
  },
  /** AuthService registers how to rotate the token; the client middleware calls this on a 401. */
  setRefreshHandler(handler: RefreshHandler | null): void {
    refreshHandler = handler;
  },
  /** Returns a fresh access token, or null if no session / the refresh failed. */
  async refresh(): Promise<string | null> {
    return refreshHandler ? refreshHandler() : null;
  },
};
