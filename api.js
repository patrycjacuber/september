const BASE_URL = 'https://outlet.creditagricole/outlet/backend/public/api'

export const getProductById = async (id) => {
  try {
    const res = await fetch(`${BASE_URL}/products.php?id=${id}`);
    if (!res.ok) throw new Error('Network response was not ok');
    return await res.json();
  } catch (error) {
    console.error('Error fetching product', error)
    return [];
  }
};

export const getAllProducts = async () => {
  try {
    const res = await fetch(`${BASE_URL}/products.php`);
    if (!res.ok) throw new Error('Network response was not ok');
    return await res.json();
  } catch (error) {
    console.error('Error fetching product', error)
    return [];
  }
};

export const addToCart = async (productId, quantity = 1) => {
  const res = await fetch(`${BASE_URL}/cart.php`, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      product_id: productId,
      quantity
    })
  });

  const data = await res.json();
  if (!res.ok) throw new Error(data.error || 'Błąd dodawania do koszyka');
  return data;
}

// dodac funkcje do usuwania z koszykja
// await fetch('/outlet/backend/public/api/cart.php', {
//   method: 'DELETE',
//   headers: { 'Content-Type': 'application/json' },
//   body: JSON.stringify({ product_id: productId })
// });

export const removeFromCart = async (productId) => {
  try {
    const res = await fetch(`${BASE_URL}/cart.php`, {
      method: 'DELETE',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ product_id: productId })
    });

    const data = await res.json();
    return data;
  } catch (error) {
    console.error('Błąd przy usuwaniu produktu:', error);
  }

};