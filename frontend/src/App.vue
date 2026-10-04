<script setup>
import { onMounted, ref } from 'vue'

const apiBaseUrl = import.meta.env.VITE_API_BASE_URL
const products = ref([])
const isLoading = ref(true)
const errorMessage = ref('')

onMounted(async () => {
  try {
    const response = await fetch(`${apiBaseUrl}/products`)

    if (!response.ok) {
      throw new Error(`Ошибка HTTP: ${response.status}`)
    }

    products.value = await response.json()
  } catch (error) {
    errorMessage.value = 'Не удалось загрузить товары. Попробуйте обновить страницу.'
    console.error('Не удалось загрузить товары:', error)
  } finally {
    isLoading.value = false
  }


})
</script>

<template>
  <main>
    <h1>Пицца и напитки</h1>

    <p v-if="isLoading">Загружаем товары…</p>
    <p v-else-if="errorMessage" role="alert">{{ errorMessage }}</p>
    <p v-else-if="products.length === 0">Товаров пока нет.</p>

    <ul v-else>
      <li v-for="product in products" :key="product.id">
        <h2>{{ product.name }}</h2>
        <p>{{ product.description }}</p>
      </li>
    </ul>
  </main>
</template>

<style scoped>
ul {
  list-style: none;
  padding: 0;
}

li {
  margin-bottom: 16px;
  padding: 16px;
  border: 1px solid #888;
  border-radius: 8px;
}

h2 {
  margin: 0 0 8px;
}

p {
  margin: 0;
}
</style>

