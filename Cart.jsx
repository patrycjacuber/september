import React, { useContext, useEffect, useState } from 'react'
import Title from '../components/Title';
import { Link } from 'react-router-dom';
import { ShopContext } from '../context/ShopContext';

const MAX_QUANTITY_IN_BASKET = 2;

const Cart = () => {
  // const [cartItems, setCartItems] = useState([]);
  const { addToCart, removeFromCart, cartItems, loadingCart } = useContext(ShopContext);


  // Pobierz dane z backendu
  // const fetchCart = async () => {
  //   try {
  //     const res = await fetch('/outlet/backend/public/api/cart.php');
  //     const data = await res.json();
  //     setCartItems(data.items || []);
  //   } catch (error) {
  //     console.error('Błąd podczas pobierania koszyka:', error);
  //   } finally {
  //     setLoading(false);
  //   }
  // };

  // useEffect(() => {
  //   setLoading(false)
  //   // fetchCart();
  // }, [cartItems]);

  // Usuń produkt z koszyka
  // const deleteFromCart = async (productId) => {
  //   try {
  //     await fetch('/outlet/backend/public/api/cart.php', {
  //       method: 'DELETE',
  //       headers: { 'Content-Type': 'application/json' },
  //       body: JSON.stringify({ product_id: productId })
  //     });
  //     // Odśwież dane
  //     fetchCart();
  //   } catch (error) {
  //     console.error('Błąd przy usuwaniu produktu:', error);
  //   }
  // };

  // const updateQuantity = async (productId, newQuantity) => {
  //   try {
  //     await addToCart(productId, newQuantity)
  //     // fetchCart();
  //   } catch (error) {
  //     console.error('Błąd przy aktualizacji ilości:', error);
  //   }
  // }

  const totalAmount = cartItems.reduce((sum, item) => sum + item.price * item.quantity, 0);

  return (
    <div className="min-h-screen p-6 border-t">

      <Title text1={'TWÓJ'} text2={'KOSZYK'} />

      {loadingCart ? (
        <p>Ładowanie...</p>
      ) : cartItems.length === 0 ? (
        <p>Koszyk jest pusty.</p>
      ) : (
        <>
          <div className="flex flex-col gap-4">
            {cartItems.map((item) => (

              <div key={item.id}
                className="flex items-center justify-between border-b pb-4">

                {/* LEWA STRONA */}

                <div className="flex items-center gap-4 flex-1">
                  <img src={item.image} alt={item.name} className="w-20 h-20 object-cover" />
                  <div>
                    <h2 className="text-lg font-semibold">{item.name}</h2>
                    <p>Ilość: {item.quantity}</p>
                    <p className="text-gray-500">{item.price} zł</p>
                  </div>
                </div>

                <div className='flex flex-row items-center gap-2 w-28 justify-center sm:gap-3 align-center'>
                  <button disabled={item.quantity <= 1} onClick={() => addToCart(item.id, -1)} className='text-gray-500'>-</button>
                  <span>{item.quantity}</span>
                  <button disabled={item.quantity >= MAX_QUANTITY_IN_BASKET} onClick={() => addToCart(item.id, 1)} className='text-gray-500'>+</button>
                </div>

                <button onClick={() => removeFromCart(item.id)} className="text-red-600 hover:underline">
                  Usuń
                </button>

              </div>
            ))}
          </div>

          <div className="mt-6">
            <p className="text-xl font-semibold">Suma: {totalAmount.toFixed(2)} zł</p>
            <Link to="/outlet/placeorder">
              <button className="mt-4 bg-cyan-700 text-white px-6 py-3 text-sm">
                Przejdź do zamówienia
              </button>
            </Link>
          </div>
        </>
      )}
    </div>
  );
};

export default Cart
