/**
 * The in-memory access token the API client attaches as a Bearer (P1-03). Kept as a tiny standalone
 * module so the client middleware can read it without depending on Angular DI (which would create a
 * cycle: AuthService → client → AuthService). AuthService is the only writer; it sets the short-lived
 * access token after an OTP verify / refresh and clears it on logout. Refresh tokens never live here.
 */
let accessToken: string | null = null;

export const tokenStore = {
  get(): string | null {
    return accessToken;
  },
  set(token: string | null): void {
    accessToken = token;
  },
};
