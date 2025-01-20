import axios from 'axios'

export const HTTP = () => {
    const origin = process.env.MIX_API_ENDPOINT
    return axios.create({
        baseURL: origin + '/',
        headers: {
            Authorization: 'Bearer ' + localStorage.getItem('token')
        }
    })
}
