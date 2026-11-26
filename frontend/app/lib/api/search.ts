import { requestJson } from "@/app/lib/api";

export type SearchProduct = {
  id?: string | number;
  name?: string;
  price?: number | string;
  currency?: string;
  category_name?: string;
  is_in_stock?: boolean;
};

export type FacetCategory = {
  id: number | string;
  name: string;
  count: number;
};

export type FacetBrand = {
  value: string;
  count: number;
};

export type FacetAttributeBucket = {
  key: string;
  values: Array<{ value: string; count: number }>;
};

export type SearchResponse = {
  data: SearchProduct[];
  meta: {
    total: number;
    page: number;
    per_page: number;
  };
  facets: {
    categories: FacetCategory[];
    in_stock: { true: number; false: number };
    price: { min: number | null; max: number | null };
    brands?: Array<{ name?: string; value?: string; count: number }>;
    attributes?: FacetAttributeBucket[];
  };
};

export type SearchQueryParams = {
  q?: string;
  category_id?: string;
  brand?: string;
  in_stock?: string;
  sort?: string;
  page?: number;
  per_page?: number;
};

export function extractBrandFacets(facets: SearchResponse["facets"] | undefined): FacetBrand[] {
  if (!facets) {
    return [];
  }

  if (Array.isArray(facets.brands) && facets.brands.length > 0) {
    return facets.brands
      .map((item) => ({
        value: String(item.value ?? item.name ?? "").trim(),
        count: item.count,
      }))
      .filter((item) => item.value !== "");
  }

  const brandBucket = (facets.attributes ?? []).find((bucket) => {
    const key = bucket.key.trim().toLowerCase();
    return key === "brand" || key === "бренд";
  });

  return (brandBucket?.values ?? []).filter((item) => item.value.trim() !== "");
}

export async function searchCatalog(
  query: SearchQueryParams,
  signal?: AbortSignal,
): Promise<SearchResponse> {
  const params = new URLSearchParams();
  if (query.q) params.set("q", query.q);
  if (query.category_id) params.set("category_id", query.category_id);
  if (query.brand) params.set("brand", query.brand);
  if (query.in_stock === "true" || query.in_stock === "false") {
    params.set("in_stock", query.in_stock);
  }
  if (query.sort) params.set("sort", query.sort);
  if (query.page && query.page > 1) params.set("page", String(query.page));
  if (query.per_page) params.set("per_page", String(query.per_page));

  return requestJson<SearchResponse>(`/api/search?${params.toString()}`, { signal });
}
