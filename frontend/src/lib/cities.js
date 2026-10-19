export const CURATED_CITIES = [
  { city: "Đà Lạt", country: "Việt Nam", region: "Lâm Đồng" },
  { city: "Đà Nẵng", country: "Việt Nam", region: "Miền Trung" },
  { city: "Phú Quốc", country: "Việt Nam", region: "Kiên Giang" },
  { city: "Nha Trang", country: "Việt Nam", region: "Khánh Hòa" },
  { city: "Hội An", country: "Việt Nam", region: "Quảng Nam" },
  { city: "Sa Pa", country: "Việt Nam", region: "Lào Cai" },
  { city: "Hạ Long", country: "Việt Nam", region: "Quảng Ninh" },
  { city: "Ninh Bình", country: "Việt Nam", region: "Đồng bằng sông Hồng" },
  { city: "Hà Nội", country: "Việt Nam", region: "Thủ đô" },
  { city: "TP. Hồ Chí Minh", country: "Việt Nam", region: "Đông Nam Bộ" },
  { city: "Vũng Tàu", country: "Việt Nam", region: "Bà Rịa - Vũng Tàu" },
  { city: "Huế", country: "Việt Nam", region: "Thừa Thiên Huế" },
  { city: "Quy Nhơn", country: "Việt Nam", region: "Bình Định" },
  { city: "Phan Thiết", country: "Việt Nam", region: "Bình Thuận" },
  { city: "Cần Thơ", country: "Việt Nam", region: "Đồng bằng sông Cửu Long" },
];

export function filterCities(query, limit = 7) {
  const q = (query || "").trim().toLowerCase();
  if (!q) return CURATED_CITIES.slice(0, limit);
  return CURATED_CITIES.filter(
    (c) =>
      c.city.toLowerCase().includes(q) ||
      c.country.toLowerCase().includes(q) ||
      (c.region?.toLowerCase().includes(q) ?? false)
  ).slice(0, limit);
}
