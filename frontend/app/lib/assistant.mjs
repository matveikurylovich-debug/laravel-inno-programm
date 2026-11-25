export function productHref(id) {
  if (!Number.isInteger(id) || id <= 0) {
    return null;
  }

  return `/catalog/products/${id}`;
}

export function assistantLinkHrefs(products) {
  return products
    .map((product) => productHref(product.product_id))
    .filter((href) => href !== null);
}
