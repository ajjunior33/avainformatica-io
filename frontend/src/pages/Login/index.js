import React, { useState } from 'react';
import api from '../../services/api';

export default function Login() {
    const [email, setEmail] = useState('');
    async function handleSubmit(event) {
        event.preventDefault();
        const response = await api.post("/sessios", { email });
        const { _id } = response.data;
        localStorage.setItem('user', _id);
    }
    return ( 
        <>
        <h1> Teste </h1> 
        </>
    )
}