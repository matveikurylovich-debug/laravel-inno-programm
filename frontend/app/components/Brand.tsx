type BrandProps = {
  tone?: "light" | "dark";
};

export default function Brand({ tone = "light" }: BrandProps) {
  return (
    <span className="brand">
      <span className="brand-mark" aria-hidden="true">
        I
      </span>
      <span className={`text-[0.95rem] ${tone === "dark" ? "text-white" : "text-slate-900"}`}>InnoTrainne</span>
    </span>
  );
}
