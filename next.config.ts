import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  serverExternalPackages: ["pg", "@electric-sql/pglite", "exceljs"],
  poweredByHeader: false,
};

export default nextConfig;
