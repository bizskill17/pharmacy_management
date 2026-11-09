"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";

export function HomeLink() {
  return usePathname() === "/" ? null : <Link className="btn" href="/">Home</Link>;
}
