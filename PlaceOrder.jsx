import React, { useState, useEffect, useContext } from 'react';
import { useNavigate } from 'react-router-dom';
import Title from '../components/Title';
import { ShopContext } from '../context/ShopContext'

const PlaceOrder = () => {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const [warnings, setWarnings] = useState([]);
  const [cartSummary, setCartSummary] = useState([]);

  const { clearCart } = useContext(ShopContext);


  const [agree1, setAgree1] = useState(false);
  const [agree2, setAgree2] = useState(false);

  const [errorAgree1, serErrorAgree1] = useState(null);
  const [errorAgree2, serErrorAgree2] = useState(null);

  const navigate = useNavigate();

  useEffect(() => {
    const fetchCartSummary = async () => {
      try {
        const res = await fetch('/outlet/backend/public/api/cart.php');
        const data = await res.json();
        setCartSummary(data.items);
      } catch (err) {
        console.error('Błąd ładowania koszyka:', err);
      }
    };
    fetchCartSummary();
  }, []);


  const placeOrder = async () => {
    setLoading(true);
    setError(null);
    setWarnings([]);

    //zgody - na przyszłość


    try {
      const res = await fetch('/outlet/backend/public/api/order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          items: cartSummary.map((i) => ({
            id: i.id,
            quantity: i.quantity
          }))
        })
      });

      const data = await res.json();

      if (!res.ok) {
        // Obsługa różnych statusów
        if (res.status === 409 && data.items) {
          // Brak stanu magazynowego
          setWarnings(
            data.items.map(
              (it) =>
                `Brak wystarczającego stanu dla ${it.name}. Dostępne: ${it.available}, chciałeś: ${it.requested}`
            )
          );
        } else if (res.status === 400 && data.remaining !== undefined) {
          // Limit na drop

          setWarnings([
            `
             Limit 2 szt. na osobę został przekroczony. Kupiłaś/eś już ${data.already}, próbujesz dodać ${data.requested}. 
             Możesz jeszcze kupić tylko ${data.remaining} szt.
             `
          ]);
        } else {
          setError(data.error || 'Błąd składania zamówienia');
        }
        return;
      }

      // sukces

      clearCart();

      navigate('/outlet/orders');
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  };


  return (
    <div className="min-h-screen p-6 border-t text-center">

      <Title text1={'PODSUMOWANIE'} text2={'ZAMÓWIENIA'} />

      {cartSummary.length > 0 ? (
        <div>
          {cartSummary.map((item, index) => (
            <div
              key={index}
              className='py-4 border-t border-b text-gray-700 flex flex-col md:flex-row md:items-center'
            >
              <div className='flex items-start gap-6 text-sm'>
                <img className='w-16 sm:w-12' src={item.image} alt="" />
                <div>
                  <p className='sm:text-base font-medium'>{item.name}</p>
                  <div className='flex items-center gap-3 mt-2 text-base text-gray-700'>
                    <p className='text-lg'>{(item.price * item.quantity).toFixed(2)} zł</p>
                    <p>Ilość: {item.quantity}</p>
                  </div>
                </div>
              </div>

            </div>
          ))}
        </div>
      ) : (
        <p>Koszyk jest pusty</p>
      )
      }

      <div className="mt-4 mb-4 space-y-2">
        <label className="flex items-center space-x-2">
          <input
            type="checkbox"
            checked={agree1}
            onChange={(e) => setAgree1(e.target.checked)}
            className="w-4 h-4"
          />
          <span className="text-sm">
          Składam zamówienie i akceptuję potrącenie kosztu zamówionych produktów z mojego wynagrodzenia.
           Potwierdzę to podpisem na dokumentach od zespołu CA outlet <span className='text-red-700'> (zgoda obowiązkowa)</span>.
          </span>
        </label>

        <label className="flex items-center space-x-2">
          <input
            type="checkbox"
            checked={agree2}
            onChange={(e) => setAgree2(e.target.checked)}
            className="w-4 h-4"
          />
          <span className="text-sm">
          Potwierdzam, że odbiorę sprzęt osobiście w Business Garden Wrocław<span className='text-red-700'> (zgoda obowiązkowa)</span>.
          </span>
        </label>
      </div>

      {/* ostrzeżenia i błędy */}
      {warnings.length > 0 && (
        <div className="mb-4 text-yellow-700 bg-yellow-100 p-3 rounded">
          {warnings.map((w, i) => (
            <p key={i}>{w}</p>
          ))}
        </div>
      )}

      {error && (
        <p className="text-red-600 mb-4">
          {error}
        </p>
      )}

      {loading ? (
        <p>Trwa składanie zamówienia...</p>
      ) : (
        <button
          className="bg-cyan-700 text-white px-6 py-3 text-sm mt-2"
          onClick={placeOrder}
          disabled={cartSummary.length === 0 || !agree1 || !agree2}
        >
          ZŁÓŻ ZAMÓWIENIE
        </button>
      )}
    </div>
  );
};

export default PlaceOrder;