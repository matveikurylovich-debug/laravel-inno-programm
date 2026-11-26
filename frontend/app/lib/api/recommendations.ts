import { requestJson } from "@/app/lib/api";

export type SuggestedProduct = {
  product_id: number;
  name: string;
  price: string | number;
  currency?: string;
  category?: string;
  similarity?: string | number;
};

export type RecommendationChatPayload = {
  message: string;
  store_id: number;
  conversation_id: string | null;
};

export type RecommendationChatResponse = {
  conversation_id: string;
  reply: string;
  products: SuggestedProduct[];
};

export async function postRecommendationChat(
  payload: RecommendationChatPayload,
  signal?: AbortSignal,
): Promise<RecommendationChatResponse> {
  return requestJson<RecommendationChatResponse>("/api/recommendations/chat", {
    method: "POST",
    body: JSON.stringify(payload),
    signal,
  });
}
