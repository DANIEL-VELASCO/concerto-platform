library(catR)

getScore = function(item, response) {
  defaultScore = 0
  if(!is.null(response) && !is.null(item$responseOptions) && item$responseOptions != "") {
    responseOptions = fromJSON(item$responseOptions)
    defaultScore = responseOptions$defaultScore
    if(length(responseOptions$scoreMap) > 0) {
      for(i in 1:length(responseOptions$scoreMap)) {
        sm = responseOptions$scoreMap[[i]]
        if(sm$value == response) {
          defaultScore = sm$score
          break
        }
      }
    }
  }

  score = as.numeric(defaultScore)
  if(!is.na(settings$responseScoreModule) && settings$responseScoreModule != "") {
    score = concerto.test.run(settings$responseScoreModule, params=list(
      item=item,
      response=response,
      score=score,
      settings=settings
    ))$score
  }
  return(as.numeric(score))
}

getTraits = function(item, value) {
  traitList = NULL

  #item level traits
  itemTrait = trimws(item$trait)
  if(!is.na(itemTrait) && length(itemTrait) > 0 && itemTrait != "") {
    traits = strsplit(itemTrait,",")[[1]]
    if(length(traits) > 0) {
      for(j in 1:length(traits)) {
        trait = trimws(traits[j])
        if(trait == "") { next }
        traitList = unique(c(traitList, trait))
      }
    }
  }

  #response level trait
  responseOptions = fromJSON(item$responseOptions)
  if(length(responseOptions$scoreMap) > 0) {
    for(j in 1:length(responseOptions$scoreMap)) {
      sm = responseOptions$scoreMap[[j]]
      if(sm$value == value) {
        responseTrait = trimws(sm$trait)
        concerto.log(responseTrait, "responseTrait")

        if(!is.na(responseTrait) && length(responseTrait) > 0 && responseTrait != "") {
          traits = strsplit(responseTrait,",")[[1]]
          if(length(traits) > 0) {
            for(k in 1:length(traits)) {
              trait = trimws(traits[k])
              if(trait == "") { next }
              traitList = unique(c(traitList, trait))
            }
          }
        }
        break
      }
    }
  }
  
  return(paste(sort(traitList), collapse=","))
}

calculateTraitScores = function(itemsAdministered, responses) {
  traitScores = list()
  for(i in 1:length(itemsAdministered)) {
    itemIndex = itemsAdministered[i]
    value = responses[i]
    item = items[itemIndex,]

    traitList = getTraits(item, value)

    #sum scores
    if(length(traitList) > 0) {
      for(j in 1:length(traitList)) {
        trait = traitList[j]
        if(trait == "") { next }
        if(is.null(traitScores[[trait]])) { traitScores[[trait]] = 0 }
        traitScores[[trait]] = traitScores[[trait]] + score
      }
    }
  }
  return(traitScores)
}

currentScores = NULL
currentTraits = NULL
for(i in 1:length(itemsIndices)) {
  item = items[itemsIndices[i],]
  responseRaw = templateResponse[[paste0("r",item$id)]]
  score = getScore(item, responseRaw)

  index = which(itemsAdministered==itemsIndices[i])
  if(length(index) > 0) {
    scores[index] = score
    responses[index] = responseRaw
  } else {
    scores = c(scores, score)
    responses = c(responses, responseRaw)
  }
  currentScores = c(currentScores, score)
  currentTraits = c(currentTraits, getTraits(item, responseRaw))
}

itemsAdministered = unique(c(itemsAdministered, itemsIndices))
paramBankAdministered = paramBank
if(dim(items)[1] > 1) {
  paramBankAdministered = paramBank[itemsAdministered,]
}

concerto.log(itemsAdministered, "itemsAdministered")
concerto.log(scores, "scores")

if(!is.na(settings$calculateTheta) && settings$calculateTheta == 1) {
  theta <- thetaEst(matrix(paramBankAdministered, ncol=as.numeric(settings$itemParamsNum), byrow=F), scores, model=settings$model, method=settings$scoringMethod)
  concerto.log(theta, "theta")
}

if(!is.na(settings$calculateSem) && settings$calculateSem == 1) {
  sem <- semTheta(theta, matrix(paramBankAdministered, ncol=as.numeric(settings$itemParamsNum), byrow=F), scores, model=settings$model, method=settings$scoringMethod)
  concerto.log(sem, "SEM")
}

traitScores = calculateTraitScores(itemsAdministered, responses)