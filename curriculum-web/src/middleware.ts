import { NextResponse, type NextRequest } from "next/server";

const TOKEN_COOKIE = "rps_token";
const LOGIN_PATH = "/login";

/**
 * Proteksi rute: tanpa token → /login; sudah login tapi buka /login → /dashboard.
 * Menyisipkan header x-pathname agar layout dapat mendeteksi halaman aktif.
 */
export function middleware(req: NextRequest) {
  const { pathname } = req.nextUrl;
  const hasToken = Boolean(req.cookies.get(TOKEN_COOKIE)?.value);

  const requestHeaders = new Headers(req.headers);
  requestHeaders.set("x-pathname", pathname);

  if (pathname === LOGIN_PATH) {
    if (hasToken) {
      return NextResponse.redirect(new URL("/dashboard", req.url));
    }
    return NextResponse.next({ request: { headers: requestHeaders } });
  }

  if (!hasToken) {
    const url = new URL(LOGIN_PATH, req.url);
    return NextResponse.redirect(url);
  }

  return NextResponse.next({ request: { headers: requestHeaders } });
}

export const config = {
  // Semua rute kecuali aset statis & internal Next.
  matcher: ["/((?!_next/static|_next/image|favicon.ico|.*\\.(?:png|jpg|jpeg|svg|ico|webp)$).*)"],
};
