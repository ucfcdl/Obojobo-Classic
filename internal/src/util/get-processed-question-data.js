export default (aGroup) => {
	// first run through all the the questions and total up the alternates
	const altCounts = []
	aGroup.kids.forEach(q => {
		if(typeof altCounts[q.questionIndex] == 'undefined') altCounts[q.questionIndex] = 1
		else altCounts[q.questionIndex] = altCounts[q.questionIndex] + 1
	})

	const questionsById = {}
	let currentQuestionIndex = -1
	let currentAltIndex
	aGroup.kids.forEach(q => {
		if(q.questionIndex !== currentQuestionIndex){
			// reset the counters if q.questionInex changes
			currentQuestionIndex = q.questionIndex
			currentAltIndex = 1
		} else {
			// same question as before, increment alt index
			currentAltIndex++
		}

		questionsById[q.questionID] = {
			questionNumber: currentQuestionIndex,
			altNumber: currentAltIndex,
			altTotal: altCounts[q.questionIndex],
			type: q.itemType,
			questionItems: q.items,
			originalQuestion: q
		}
	})

	return questionsById
}
