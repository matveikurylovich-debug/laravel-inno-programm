export type AuthUser = {
  id: number;
  name: string;
  email: string;
  phone: string | null;
  role: string;
};

export type AuthResponse = {
  access_token: string;
  token_type: string;
  expires_in: number;
  user: AuthUser;
};

export type TwoFactorChallenge = {
  two_factor: true;
  user_id: number;
  expires_in: number;
  message: string;
};

export type DirectoryUser = {
  id: number;
  email: string;
  role: string;
  registered_at: string | null;
};

export type NotificationLog = {
  id: string;
  event: string | null;
  channel: string | null;
  recipient: string | null;
  status: string | null;
  attempts: number;
  is_dlq: boolean;
  error_message: string | null;
  payload: unknown;
  sent_at: string | null;
  created_at: string | null;
};

export type PaginatedNotifications = {
  data: NotificationLog[];
  meta?: {
    current_page: number;
    last_page: number;
    total: number;
  };
};
