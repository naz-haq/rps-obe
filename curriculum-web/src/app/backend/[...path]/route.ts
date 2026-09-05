import { NextRequest } from "next/server";
import { API_BASE_URL } from "@/lib/api";
import { getToken } from "@/lib/auth";

const FORWARDED_RESPONSE_HEADERS = [
  "cache-control",
  "content-disposition",
  "content-length",
  "content-type",
  "etag",
  "last-modified",
] as const;

export async function GET(
  request: NextRequest,
  { params }: { params: Promise<{ path: string[] }> },
) {
  const token = await getToken();
  if (!token) {
    return Response.json({ message: "Unauthenticated." }, { status: 401 });
  }

  const { path } = await params;
  const apiBase = new URL(API_BASE_URL);
  const target = new URL(`/${path.map(encodeURIComponent).join("/")}`, apiBase.origin);
  target.search = request.nextUrl.search;

  const upstream = await fetch(target, {
    headers: {
      Accept: "application/json",
      Authorization: `Bearer ${token}`,
      ...(request.headers.get("range") ? { Range: request.headers.get("range")! } : {}),
    },
    cache: "no-store",
    redirect: "manual",
  });

  const headers = new Headers();
  for (const name of FORWARDED_RESPONSE_HEADERS) {
    const value = upstream.headers.get(name);
    if (value) headers.set(name, value);
  }

  return new Response(upstream.body, {
    status: upstream.status,
    headers,
  });
}