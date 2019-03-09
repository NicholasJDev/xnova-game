export default {
	PAGE_LOAD (state, data)
	{
		state.start_time = Math.floor(((new Date()).getTime()) / 1000)

		for (let key in data)
		{
			if (data.hasOwnProperty(key))
			{
				state[key] = data[key];

				//if (key === 'page')
				//	state.loaded = false
			}
		}
	},
	setLoadingStatus (state, status) {
		state.loading = status
	}
}