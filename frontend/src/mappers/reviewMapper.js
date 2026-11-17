function invalidResponse() {
  return Object.assign(new Error('Phản hồi đánh giá không hợp lệ.'), { code: 'INVALID_REVIEW_RESPONSE' });
}

function isRecord(value) {
  return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function count(value, minimum = 0) {
  const numeric = typeof value === 'string' && /^\d+$/.test(value) ? Number(value) : value;
  if (!Number.isSafeInteger(numeric) || numeric < minimum) throw invalidResponse();
  return numeric;
}

function mapReview(review, audience) {
  if (!isRecord(review) || !Number.isSafeInteger(review.id) || review.id < 1
    || !Number.isInteger(review.rating) || review.rating < 1 || review.rating > 5
    || typeof review.comment !== 'string'
    || (review.created_at !== null && typeof review.created_at !== 'string')) throw invalidResponse();

  if (audience === 'public') {
    if (typeof review.author !== 'string') throw invalidResponse();
    const { id, rating, comment, author, created_at } = review;
    return { id, rating, comment, author, created_at };
  }

  if (!Number.isSafeInteger(review.booking_id) || review.booking_id < 1) throw invalidResponse();
  return review;
}

function mapSummary(summary, countKey) {
  if (!isRecord(summary) || !isRecord(summary.rating_breakdown)) throw invalidResponse();
  const average = summary.average_rating;
  if (average !== null && (!Number.isFinite(average) || average < 0 || average > 5)) throw invalidResponse();
  const ratingBreakdown = Object.fromEntries(
    [1, 2, 3, 4, 5].map((stars) => [stars, count(summary.rating_breakdown[stars])])
  );
  return {
    [countKey]: count(summary[countKey]),
    average_rating: average,
    rating_breakdown: ratingBreakdown,
  };
}

export function mapReviewPage(response, audience) {
  if (!isRecord(response) || !Array.isArray(response.data) || !isRecord(response.meta)
    || !isRecord(response.links)) throw invalidResponse();

  const { meta, links } = response;
  const result = {
    data: Array.from(response.data, (review) => mapReview(review, audience)),
    meta,
    links,
    pagination: {
      currentPage: count(meta.current_page, 1),
      lastPage: count(meta.last_page, 1),
      perPage: count(meta.per_page, 1),
      total: count(meta.total),
    },
  };
  if (audience === 'public') result.summary = mapSummary(response.summary, 'review_count');
  if (audience === 'host') result.summary = mapSummary(response.summary, 'total_reviews');
  return result;
}

export function mapCreatedReview(response) {
  if (!isRecord(response)) throw invalidResponse();
  return { data: mapReview(response.data, 'customer') };
}

export function formatReviewDate(value) {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleDateString('vi-VN', {
    day: 'numeric',
    month: 'long',
    year: 'numeric',
  });
}

export function mapReviewCard(review) {
  let bookingReference = null;
  if (review.booking_code && String(review.booking_code).trim()) {
    bookingReference = `Mã đơn: ${review.booking_code}`;
  } else if (review.booking_id) {
    bookingReference = `Tham chiếu đặt phòng #${review.booking_id}`;
  }

  return {
    bookingReference,
    dateLabel: formatReviewDate(review.created_at),
    propertyName: review.property_name || 'Chỗ nghỉ chưa có thông tin tên',
    ratingStars: Math.min(5, Math.max(1, Number(review.rating) || 5)),
  };
}
