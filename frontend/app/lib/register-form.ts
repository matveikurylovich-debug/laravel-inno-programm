export type RegisterField = "name" | "email" | "phone" | "password" | "passwordConfirmation";

export type RegisterInput = {
  name: string;
  email: string;
  phone: string;
  password: string;
  passwordConfirmation: string;
};

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function registerFieldErrors(input: RegisterInput): Partial<Record<RegisterField, string>> {
  const errors: Partial<Record<RegisterField, string>> = {};
  const name = input.name.trim();
  const email = input.email.trim();
  const phoneDigits = input.phone.replace(/\D/g, "");

  if (name === "") {
    errors.name = "Укажите имя.";
  }

  if (!EMAIL_PATTERN.test(email)) {
    errors.email = "Укажите полный адрес электронной почты, например name@example.com.";
  }

  const belarusPhone =
    (phoneDigits.startsWith("375") && phoneDigits.length === 12) ||
    (phoneDigits.startsWith("80") && phoneDigits.length === 11);

  if (input.phone.trim() === "") {
    errors.phone = "Укажите телефон.";
  } else if (!belarusPhone) {
    errors.phone = "Укажите телефон в формате +375 (29) 123-45-67.";
  }

  if (input.password.length < 8) {
    errors.password = "Пароль должен содержать не меньше 8 символов.";
  }

  if (input.passwordConfirmation !== input.password) {
    errors.passwordConfirmation = "Подтверждение пароля не совпадает.";
  }

  return errors;
}
