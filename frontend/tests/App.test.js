import { afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import App from '../src/App.vue'

let wrapper

afterEach(() => {
  wrapper?.unmount()
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
})

describe('Product catalog', () => {
  it('shows loading until the request completes, then renders products', async () => {
    let respond
    const fetchMock = vi.fn(() => new Promise((resolve) => { respond = resolve }))
    vi.stubGlobal('fetch', fetchMock)
    wrapper = mount(App)

    expect(wrapper.text()).toContain('Загружаем товары…')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(fetchMock).toHaveBeenCalledWith('/api/products')

    respond({ ok: true, json: async () => [
      { id: 1, name: 'Маргарита', description: 'Томаты и моцарелла' },
      { id: 2, name: 'Лимонад', description: 'Лимонный напиток' },
    ] })
    await flushPromises()

    expect(wrapper.findAll('li')).toHaveLength(2)
    expect(wrapper.text()).toContain('Маргарита')
    expect(wrapper.text()).toContain('Лимонный напиток')
    expect(wrapper.text()).not.toContain('Загружаем товары…')
    expect(wrapper.text()).not.toContain('Товаров пока нет.')
  })

  it('shows the empty state for an empty API response', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ ok: true, json: async () => [] }))
    wrapper = mount(App)
    await flushPromises()

    expect(wrapper.text()).toContain('Товаров пока нет.')
    expect(wrapper.find('ul').exists()).toBe(false)
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it.each(['http', 'network'])('shows an error for a %s failure', async (failure) => {
    vi.spyOn(console, 'error').mockImplementation(() => {})
    const fetchMock = vi.fn()
    if (failure === 'http') {
      fetchMock.mockResolvedValue({ ok: false, status: 503 })
    } else {
      fetchMock.mockRejectedValue(new TypeError('Network unavailable'))
    }
    vi.stubGlobal('fetch', fetchMock)
    wrapper = mount(App)
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toBe(
      'Не удалось загрузить товары. Попробуйте обновить страницу.',
    )
    expect(wrapper.text()).not.toContain('Загружаем товары…')
    expect(wrapper.text()).not.toContain('Товаров пока нет.')
    expect(wrapper.find('ul').exists()).toBe(false)
  })
})
