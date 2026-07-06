"use client";

import { useTransition } from "react";
import { useRouter } from "next/navigation";
import { buttonClass } from "@/components/ui";
import { tandaiDibaca } from "./actions";

export function TandaiDibacaButton({ id }: { id: number }) {
  const [pending, start] = useTransition();
  const router = useRouter();

  return (
    <button
      type="button"
      disabled={pending}
      onClick={() =>
        start(async () => {
          await tandaiDibaca(id);
          router.refresh();
        })
      }
      className={buttonClass("secondary", "sm")}
    >
      {pending ? "Menyimpan…" : "Tandai dibaca"}
    </button>
  );
}
