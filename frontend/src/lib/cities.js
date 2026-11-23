function removeAccents(str = '') {
  return str
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/đ/g, 'd')
    .replace(/Đ/g, 'D')
    .toLowerCase();
}

export const CURATED_CITIES = [
  // --- Điểm đến du lịch & thành phố thịnh hành nhất ---
  { city: "Đà Lạt", country: "Việt Nam", region: "Lâm Đồng", aliases: ["Lâm Đồng", "Lam Dong", "Da Lat"] },
  { city: "Đà Nẵng", country: "Việt Nam", region: "Miền Trung", aliases: ["Bà Nà", "Da Nang", "Danang"] },
  { city: "Phú Quốc", country: "Việt Nam", region: "Kiên Giang", aliases: ["Kien Giang", "Phu Quoc"] },
  { city: "Nha Trang", country: "Việt Nam", region: "Khánh Hòa", aliases: ["Khanh Hoa", "Nha Trang"] },
  { city: "Vũng Tàu", country: "Việt Nam", region: "Bà Rịa - Vũng Tàu", aliases: ["Ba Ria - Vung Tau", "Vung Tau"] },
  { city: "Hội An", country: "Việt Nam", region: "Quảng Nam", aliases: ["Quang Nam", "Hoi An"] },
  { city: "Sa Pa", country: "Việt Nam", region: "Lào Cai", aliases: ["Lao Cai", "Fansipan", "Sapa"] },
  { city: "Hạ Long", country: "Việt Nam", region: "Quảng Ninh", aliases: ["Quang Ninh", "Ha Long", "Halong"] },
  { city: "Ninh Bình", country: "Việt Nam", region: "Tràng An - Tam Cốc", aliases: ["Trang An", "Ninh Binh"] },
  { city: "Hà Nội", country: "Việt Nam", region: "Thủ đô", aliases: ["HN", "Hanoi", "Ha Noi"] },
  { city: "TP. Hồ Chí Minh", country: "Việt Nam", region: "Đông Nam Bộ", aliases: ["Sài Gòn", "Sai Gon", "HCM", "HCMC", "Ho Chi Minh"] },
  { city: "Huế", country: "Việt Nam", region: "Thừa Thiên Huế", aliases: ["Thua Thien Hue", "Hue", "Cố đô"] },
  { city: "Quy Nhơn", country: "Việt Nam", region: "Bình Định", aliases: ["Binh Dinh", "Quy Nhon"] },
  { city: "Phan Thiết", country: "Việt Nam", region: "Bình Thuận", aliases: ["Mũi Né", "Mui Ne", "Binh Thuan", "Phan Thiet"] },
  { city: "Cần Thơ", country: "Việt Nam", region: "Đồng bằng sông Cửu Long", aliases: ["Can Tho", "Tây Đô"] },
  { city: "Hải Phòng", country: "Việt Nam", region: "Đồng bằng sông Hồng", aliases: ["Cát Bà", "Cat Ba", "Đồ Sơn", "Hai Phong"] },
  { city: "Mộc Châu", country: "Việt Nam", region: "Sơn La", aliases: ["Son La", "Moc Chau"] },
  { city: "Hà Giang", country: "Việt Nam", region: "Đông Bắc Bộ", aliases: ["Đồng Văn", "Mã Pí Lèng", "Ha Giang"] },
  { city: "Côn Đảo", country: "Việt Nam", region: "Bà Rịa - Vũng Tàu", aliases: ["Con Dao", "Ba Ria - Vung Tau"] },
  { city: "Phú Yên", country: "Việt Nam", region: "Duyên hải Nam Trung Bộ", aliases: ["Tuy Hòa", "Tuy Hoa", "Phu Yen", "Gành Đá Đĩa"] },
  { city: "Buôn Ma Thuột", country: "Việt Nam", region: "Đắk Lắk", aliases: ["Dak Lak", "Buon Ma Thuot", "BMT"] },
  { city: "Đồng Hới", country: "Việt Nam", region: "Quảng Bình", aliases: ["Phong Nha", "Kẻ Bàng", "Quang Binh", "Dong Hoi"] },
  { city: "Tam Đảo", country: "Việt Nam", region: "Vĩnh Phúc", aliases: ["Vinh Phuc", "Tam Dao"] },
  { city: "Mai Châu", country: "Việt Nam", region: "Hòa Bình", aliases: ["Hoa Binh", "Mai Chau"] },
  { city: "Phan Rang", country: "Việt Nam", region: "Ninh Thuận", aliases: ["Ninh Thuan", "Phan Rang - Tháp Chàm", "Phan Rang"] },
  { city: "Bảo Lộc", country: "Việt Nam", region: "Lâm Đồng", aliases: ["Bao Loc", "Lam Dong"] },
  { city: "Pleiku", country: "Việt Nam", region: "Gia Lai", aliases: ["Gia Lai", "Biển Hồ", "Bien Ho"] },
  { city: "Châu Đốc", country: "Việt Nam", region: "An Giang", aliases: ["An Giang", "Chau Doc", "Miếu Bà"] },
  { city: "Sầm Sơn", country: "Việt Nam", region: "Thanh Hóa", aliases: ["Thanh Hoa", "Sam Son"] },
  { city: "Cửa Lò", country: "Việt Nam", region: "Nghệ An", aliases: ["Nghe An", "Cua Lo", "Vinh"] },
  { city: "Tây Ninh", country: "Việt Nam", region: "Đông Nam Bộ", aliases: ["Núi Bà Đen", "Tay Ninh"] },

  // --- Danh sách đầy đủ các Tỉnh / Thành phố Việt Nam ---
  { city: "An Giang", country: "Việt Nam", region: "Đồng bằng sông Cửu Long", aliases: ["Long Xuyên", "Long Xuyen"] },
  { city: "Bà Rịa - Vũng Tàu", country: "Việt Nam", region: "Đông Nam Bộ", aliases: ["Ba Ria", "Vung Tau"] },
  { city: "Bắc Giang", country: "Việt Nam", region: "Đông Bắc Bộ", aliases: ["Bac Giang"] },
  { city: "Bắc Kạn", country: "Việt Nam", region: "Đông Bắc Bộ", aliases: ["Hồ Ba Bể", "Bac Kan"] },
  { city: "Bạc Liêu", country: "Việt Nam", region: "Đồng bằng sông Cửu Long", aliases: ["Bac Lieu"] },
  { city: "Bắc Ninh", country: "Việt Nam", region: "Đồng bằng sông Hồng", aliases: ["Bac Ninh", "Kinh Bắc"] },
  { city: "Bến Tre", country: "Việt Nam", region: "Đồng bằng sông Cửu Long", aliases: ["Ben Tre", "Xứ Dừa"] },
  { city: "Bình Định", country: "Việt Nam", region: "Duyên hải Nam Trung Bộ", aliases: ["Binh Dinh"] },
  { city: "Bình Dương", country: "Việt Nam", region: "Đông Nam Bộ", aliases: ["Thủ Dầu Một", "Binh Duong"] },
  { city: "Bình Phước", country: "Việt Nam", region: "Đông Nam Bộ", aliases: ["Đồng Xoài", "Binh Phuoc"] },
  { city: "Bình Thuận", country: "Việt Nam", region: "Duyên hải Nam Trung Bộ", aliases: ["Binh Thuan"] },
  { city: "Cà Mau", country: "Việt Nam", region: "Đồng bằng sông Cửu Long", aliases: ["Đất Mũi", "Ca Mau"] },
  { city: "Cao Bằng", country: "Việt Nam", region: "Đông Bắc Bộ", aliases: ["Thác Bản Giốc", "Cao Bang"] },
  { city: "Đắk Lắk", country: "Việt Nam", region: "Tây Nguyên", aliases: ["Dak Lak"] },
  { city: "Đắk Nông", country: "Việt Nam", region: "Tây Nguyên", aliases: ["Gia Nghĩa", "Dak Nong"] },
  { city: "Điện Biên", country: "Việt Nam", region: "Tây Bắc Bộ", aliases: ["Điện Biên Phủ", "Dien Bien"] },
  { city: "Đồng Nai", country: "Việt Nam", region: "Đông Nam Bộ", aliases: ["Biên Hòa", "Dong Nai"] },
  { city: "Đồng Tháp", country: "Việt Nam", region: "Đồng bằng sông Cửu Long", aliases: ["Cao Lãnh", "Sa Đéc", "Dong Thap"] },
  { city: "Gia Lai", country: "Việt Nam", region: "Tây Nguyên", aliases: ["Gia Lai"] },
  { city: "Hà Nam", country: "Việt Nam", region: "Đồng bằng sông Hồng", aliases: ["Phủ Lý", "Tam Chúc", "Ha Nam"] },
  { city: "Hà Tĩnh", country: "Việt Nam", region: "Bắc Trung Bộ", aliases: ["Ha Tinh", "Hồng Lĩnh"] },
  { city: "Hải Dương", country: "Việt Nam", region: "Đồng bằng sông Hồng", aliases: ["Hai Duong", "Chí Linh"] },
  { city: "Hậu Giang", country: "Việt Nam", region: "Đồng bằng sông Cửu Long", aliases: ["Vị Thanh", "Hau Giang"] },
  { city: "Hòa Bình", country: "Việt Nam", region: "Tây Bắc Bộ", aliases: ["Hoa Binh", "Kim Bôi"] },
  { city: "Hưng Yên", country: "Việt Nam", region: "Đồng bằng sông Hồng", aliases: ["Hung Yen", "Phố Hiến"] },
  { city: "Khánh Hòa", country: "Việt Nam", region: "Duyên hải Nam Trung Bộ", aliases: ["Khanh Hoa", "Cam Ranh"] },
  { city: "Kiên Giang", country: "Việt Nam", region: "Đồng bằng sông Cửu Long", aliases: ["Rạch Giá", "Hà Tiên", "Kien Giang"] },
  { city: "Kon Tum", country: "Việt Nam", region: "Tây Nguyên", aliases: ["Măng Đen", "Mang Den", "Kon Tum"] },
  { city: "Lai Châu", country: "Việt Nam", region: "Tây Bắc Bộ", aliases: ["Lai Chau"] },
  { city: "Lâm Đồng", country: "Việt Nam", region: "Tây Nguyên", aliases: ["Lam Dong"] },
  { city: "Lạng Sơn", country: "Việt Nam", region: "Đông Bắc Bộ", aliases: ["Lang Son", "Mẫu Sơn"] },
  { city: "Lào Cai", country: "Việt Nam", region: "Tây Bắc Bộ", aliases: ["Lao Cai", "Bắc Hà"] },
  { city: "Long An", country: "Việt Nam", region: "Đồng bằng sông Cửu Long", aliases: ["Tân An", "Long An"] },
  { city: "Nam Định", country: "Việt Nam", region: "Đồng bằng sông Hồng", aliases: ["Nam Dinh"] },
  { city: "Nghệ An", country: "Việt Nam", region: "Bắc Trung Bộ", aliases: ["Nghe An", "Vinh"] },
  { city: "Ninh Thuận", country: "Việt Nam", region: "Duyên hải Nam Trung Bộ", aliases: ["Ninh Thuan", "Vĩnh Hy"] },
  { city: "Phú Thọ", country: "Việt Nam", region: "Đông Bắc Bộ", aliases: ["Đền Hùng", "Việt Trì", "Phu Tho"] },
  { city: "Quảng Bình", country: "Việt Nam", region: "Bắc Trung Bộ", aliases: ["Quang Binh"] },
  { city: "Quảng Nam", country: "Việt Nam", region: "Duyên hải Nam Trung Bộ", aliases: ["Quang Nam", "Tam Kỳ"] },
  { city: "Quảng Ngãi", country: "Việt Nam", region: "Duyên hải Nam Trung Bộ", aliases: ["Lý Sơn", "Ly Son", "Quang Ngai"] },
  { city: "Quảng Ninh", country: "Việt Nam", region: "Đông Bắc Bộ", aliases: ["Quang Ninh", "Vân Đồn", "Uông Bí"] },
  { city: "Quảng Trị", country: "Việt Nam", region: "Bắc Trung Bộ", aliases: ["Đông Hà", "Quang Tri"] },
  { city: "Sóc Trăng", country: "Việt Nam", region: "Đồng bằng sông Cửu Long", aliases: ["Soc Trang"] },
  { city: "Sơn La", country: "Việt Nam", region: "Tây Bắc Bộ", aliases: ["Son La"] },
  { city: "Thái Bình", country: "Việt Nam", region: "Đồng bằng sông Hồng", aliases: ["Thai Binh"] },
  { city: "Thái Nguyên", country: "Việt Nam", region: "Đông Bắc Bộ", aliases: ["Thai Nguyen", "Hồ Núi Cốc"] },
  { city: "Thanh Hóa", country: "Việt Nam", region: "Bắc Trung Bộ", aliases: ["Thanh Hoa", "Pù Luông"] },
  { city: "Thừa Thiên Huế", country: "Việt Nam", region: "Bắc Trung Bộ", aliases: ["Thua Thien Hue", "Lăng Cô"] },
  { city: "Tiền Giang", country: "Việt Nam", region: "Đồng bằng sông Cửu Long", aliases: ["Mỹ Tho", "Tien Giang"] },
  { city: "Trà Vinh", country: "Việt Nam", region: "Đồng bằng sông Cửu Long", aliases: ["Tra Vinh"] },
  { city: "Tuyên Quang", country: "Việt Nam", region: "Đông Bắc Bộ", aliases: ["Na Hang", "Tuyen Quang"] },
  { city: "Vĩnh Long", country: "Việt Nam", region: "Đồng bằng sông Cửu Long", aliases: ["Vinh Long"] },
  { city: "Vĩnh Phúc", country: "Việt Nam", region: "Đồng bằng sông Hồng", aliases: ["Vinh Phuc", "Đại Lải"] },
  { city: "Yên Bái", country: "Việt Nam", region: "Tây Bắc Bộ", aliases: ["Mù Cang Chải", "Mu Cang Chai", "Yen Bai"] },
];

export function filterCities(query, limit = 8) {
  const rawQ = (query || "").trim();
  if (!rawQ) return CURATED_CITIES.slice(0, limit);

  const qLower = rawQ.toLowerCase();
  const qNorm = removeAccents(rawQ);

  return CURATED_CITIES.filter((c) => {
    // 1. Khớp chính xác có dấu với city, country, region
    if (
      c.city.toLowerCase().includes(qLower) ||
      c.country.toLowerCase().includes(qLower) ||
      (c.region && c.region.toLowerCase().includes(qLower))
    ) {
      return true;
    }

    // 2. Khớp không dấu (unaccented) cho cả tên thành phố và vùng miền
    const cityNorm = removeAccents(c.city);
    const regionNorm = removeAccents(c.region || "");
    if (cityNorm.includes(qNorm) || regionNorm.includes(qNorm)) {
      return true;
    }

    // 3. Khớp với các tên gợi nhớ (aliases) như "Sài Gòn", "Fansipan", "Cát Bà", "HN", "BMT"...
    if (Array.isArray(c.aliases)) {
      return c.aliases.some(
        (alias) =>
          alias.toLowerCase().includes(qLower) ||
          removeAccents(alias).includes(qNorm)
      );
    }

    return false;
  }).slice(0, limit);
}
