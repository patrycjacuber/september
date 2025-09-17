import { createContext, useEffect, useState } from "react";
import { addToCart as APIAddToCart, removeFromCart as APIRemoveFromCart } from "../services/api";

export const ShopContext = createContext();

const ShopContextProvider = (props) => {
    const [search, setSearch] = useState('');
    const [showSearch, setShowSearch] = useState(false);
    const [cartItems, setCartItems] = useState([]);
    const [cartCount, setCartCount] = useState(0);
    const [loadingCart, setLoadingCart] = useState(true);


    const getStatusColor = (status) => {
        switch (status) {
            case "Gotowe do odbioru":
                return "bg-green-500";
            case "Zamówienie złożone, poczekaj na maila.":
                return "bg-yellow-500";
            case "W przygotowaniu":
                return "bg-yellow-500";
            case "Zakończone":
                return "bg-green-500";
            default:
                return "bg-gray-300";
        }
    }


    // Ładowanie koszyka z backendu
    const fetchCart = async () => {
        try {
            const res = await fetch('/outlet/backend/public/api/cart.php');
            const data = await res.json();
            setCartItems(data.items || []);
            setCartCount(data.total_count || 0);
            console.log("Response:", data)
            setLoadingCart(false)
        } catch (err) {
            console.error("Błąd pobierania koszyka:", err);
        }
    };

    useEffect(() => {
        fetchCart();
    }, []);

    // Dodawanie do koszyka
    const addToCart = async (itemId, newQuantity = 1) => {
        // try {
        await APIAddToCart(itemId, newQuantity)
        await fetchCart();
    };

    // usuwanie elementu z koszyka analogicznie do addToCart
    const removeFromCart = async (itemId) => {
        await APIRemoveFromCart(itemId)
        await fetchCart();
    };

    const clearCart = () => {
      setCartItems({});
        setCartCount(0);
    }


    const value = {
        search, setSearch, showSearch, setShowSearch,
        cartItems, cartCount, setCartItems,
        addToCart, setCartCount, removeFromCart, loadingCart, 
        getStatusColor,
        clearCart,

    };

    return (
        <ShopContext.Provider value={value}>
            {props.children}
        </ShopContext.Provider>
    );
};

export default ShopContextProvider;