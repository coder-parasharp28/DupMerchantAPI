import { createStore } from 'vuex'
import { vuex } from '../app'

const debug = process.env.NODE_ENV !== 'production'
export default createStore({
    modules: vuex,
    strict: debug
})
