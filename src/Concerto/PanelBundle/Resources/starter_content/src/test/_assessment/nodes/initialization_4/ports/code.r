getIndicedColumnsNum = function(tableName, firstColumnName) {
  columnPrefix = substring(firstColumnName, 1, nchar(firstColumnName) - 1)

  columns = concerto.table.query("SHOW COLUMNS FROM {{tableName}} LIKE '{{columnPrefix}}%'", params=list(
    tableName=tableName,
    columnPrefix=columnPrefix
  ))[,"Field"]

  i=1;
  while(paste0(columnPrefix,i) %in% columns) {
    i=i+1
  }
  return(i-1)
}

getExtraFieldsSql = function(extraFields) {
  extraFields = fromJSON(extraFields)
  extraFieldsNames = lapply(extraFields, function(extraField) { return(extraField$name) })
  extraFieldsSql = paste(extraFieldsNames, collapse=", ")  
  if(extraFieldsSql != "") { extraFieldsSql = paste0(", ", extraFieldsSql) }
  return(extraFieldsSql)
}

getIndicedColumnsSql = function(firstColumnName, num, aliasPrefix) {
  columnNamePrefix = substring(firstColumnName, 1, nchar(firstColumnName) - 1)
  columns = c()
  for(i in 1:num) {
    columnName = paste0(columnNamePrefix, i)
    alias = paste0(aliasPrefix, i)
    columns = c(columns, paste0(columnName, " AS ", alias))
  }
  return(paste(columns, collapse=", "))
}

convertFromFlat = function(items, responseColumnsNum) {
  itemsNum = dim(items)[1]
  if(itemsNum == 0) { return(items) }

  defaultScore = 0
  defaultPainMannequinGender = "male"
  defaultPainMannequinAreaMultiMarks = 1
  defaultGracelyScaleShow = "both"
  defaultOptionsRandomOrder = 0

  for(i in 1:itemsNum) {
    item = items[i,]

    options = list()
    for(j in 1:responseColumnsNum) {
      options[[j]] = list(
        label=item[[paste0("responseLabel",j)]],
        value=item[[paste0("responseValue",j)]]
      )
    }

    scoreMap = list()
    for(j in 1:responseColumnsNum) {
      scoreMap[[j]] = list(
        score=item[[paste0("responseScore",j)]],
        value=item[[paste0("responseValue",j)]],
        trait=item[[paste0("responseTrait",j)]]
      )
    }

    responseOptions = list(
      type=item$type,
      optionsRandomOrder=item$optionsRandomOrder,
      painMannequinGender=item$painMannequinGender,
      painMannequinAreaMultiMarks=item$painMannequinAreaMultiMarks,
      gracelyScaleShow=item$gracelyScaleShow,
      options=options,
      scoreMap=scoreMap,
      defaultScore=defaultScore
    )

    if(is.null(responseOptions$painMannequinGender)) { 
      responseOptions$painMannequinGender = defaultPainMannequinGender
    }
    if(is.null(responseOptions$painMannequinAreaMultiMarks)) { 
      responseOptions$painMannequinAreaMultiMarks = defaultPainMannequinAreaMultiMarks
    }
    if(is.null(responseOptions$gracelyScaleShow)) { 
      responseOptions$gracelyScaleShow = defaultGracelyScaleShow
    }
    if(is.null(responseOptions$optionsRandomOrder)) { 
      responseOptions$optionsRandomOrder = defaultOptionsRandomOrder
    }

    items[i, "responseOptions"] = toJSON(responseOptions)
  }

  return(items)
}

getItems = function(itemBankType, itemBankItems, itemBankTable, itemBankFlatTable, extraFields, paramsNum){
  items = NULL

  if(itemBankType == "table") {
    tableMap = fromJSON(itemBankTable)

    table = tableMap$table
    questionColumn = tableMap$columns$question
    responseOptionsColumn = tableMap$columns$responseOptions
    p1Column = tableMap$columns$p1
    traitColumn = tableMap$columns$trait
    fixedIndexColumn = tableMap$columns$fixedIndex

    extraFieldsSql = getExtraFieldsSql(extraFields)
    parametersSql = getIndicedColumnsSql(p1Column, paramsNum, "p")

    items = concerto.table.query(
      "
SELECT 
id, 
{{questionColumn}} AS question, 
{{responseOptionsColumn}} AS responseOptions,
{{parametersSql}},
{{traitColumn}} AS trait,
{{fixedIndexColumn}} AS fixedIndex
{{extraFieldsSql}}
FROM {{table}}
", 
      list(
        questionColumn=questionColumn,
        responseOptionsColumn=responseOptionsColumn,
        parametersSql=parametersSql,
        traitColumn=traitColumn,
        fixedIndexColumn=fixedIndexColumn,
        extraFieldsSql=extraFieldsSql,
        table=table
      ))
  }

  if(itemBankType == "flatTable") {
    tableMap = fromJSON(itemBankFlatTable)

    table = tableMap$table
    questionColumn = tableMap$columns$question
    p1Column = tableMap$columns$p1
    traitColumn = tableMap$columns$trait
    fixedIndexColumn = tableMap$columns$fixedIndex
    responseLabel1Column = tableMap$columns$responseLabel1
    responseValue1Column = tableMap$columns$responseValue1
    responseScore1Column = tableMap$columns$responseScore1
    typeColumn = tableMap$columns$type
    gracelyScaleShowColumn = tableMap$columns$gracelyScaleShow
    painMannequinGenderColumn = tableMap$columns$painMannequinGender
    painMannequinAreaMultiMarksColumn = tableMap$columns$painMannequinAreaMultiMarks
    optionsRandomOrderColumn = tableMap$columns$optionsRandomOrder

    gracelyScaleShowSql = ""
    if(!is.null(gracelyScaleShowColumn) && !is.na(gracelyScaleShowColumn) && gracelyScaleShowColumn != "") { gracelyScaleShowSql = "{{gracelyScaleShowColumn}} AS gracelyScaleShow," }
    painMannequinGenderSql = ""
    if(!is.null(painMannequinGenderColumn) && !is.na(painMannequinGenderColumn) && painMannequinGenderColumn != "") { painMannequinGenderSql = "{{painMannequinGenderColumn}} AS painMannequinGender," }
    painMannequinAreaMultiMarksSql = ""
    if(!is.null(painMannequinAreaMultiMarksColumn) && !is.na(painMannequinAreaMultiMarksColumn) && painMannequinAreaMultiMarksColumn != "") { painMannequinAreaMultiMarksSql = "{{painMannequinAreaMultiMarksColumn}} AS painMannequinAreaMultiMarks," }
    optionsRandomOrderSql = ""
    if(!is.null(optionsRandomOrderColumn) && !is.na(optionsRandomOrderColumn) && optionsRandomOrderColumn != "") { optionsRandomOrderSql = "{{optionsRandomOrderColumn}} AS optionsRandomOrder," }

    extraFieldsSql = getExtraFieldsSql(extraFields)
    parametersSql = getIndicedColumnsSql(p1Column, paramsNum, "p")
    responseColumnsNum = getIndicedColumnsNum(table, responseValue1Column)
    responseLabelSql = getIndicedColumnsSql(responseLabel1Column, responseColumnsNum, "responseLabel")
    responseValueSql = getIndicedColumnsSql(responseValue1Column, responseColumnsNum, "responseValue")
    responseScoreSql = getIndicedColumnsSql(responseScore1Column, responseColumnsNum, "responseScore")

    items = concerto.table.query(
      "
SELECT 
id, 
{{questionColumn}} AS question,
{{parametersSql}},
{{traitColumn}} AS trait,
{{fixedIndexColumn}} AS fixedIndex,
{{responseLabelSql}},
{{responseValueSql}},
{{responseScoreSql}},
{{gracelyScaleShowSql}}
{{painMannequinGenderSql}}
{{painMannequinAreaMultiMarksSql}}
{{optionsRandomOrderSql}}
{{extraFieldsSql}}
{{typeColumn}} AS type
FROM {{table}}
", 
      list(
        questionColumn=questionColumn,
        parametersSql=parametersSql,
        traitColumn=traitColumn,
        fixedIndexColumn=fixedIndexColumn,
        extraFieldsSql=extraFieldsSql,
        responseLabelSql=responseLabelSql,
        responseValueSql=responseValueSql,
        responseScoreSql=responseScoreSql,
        typeColumn=typeColumn,
        gracelyScaleShowSql=gracelyScaleShowSql,
        gracelyScaleShowColumn=gracelyScaleShowColumn,
        painMannequinGenderSql=painMannequinGenderSql,
        painMannequinGenderColumn=painMannequinGenderColumn,
        painMannequinAreaMultiMarksSql=painMannequinAreaMultiMarksSql,
        painMannequinAreaMultiMarksColumn=painMannequinAreaMultiMarksColumn,
        optionsRandomOrderSql=optionsRandomOrderSql,
        optionsRandomOrderColumn=optionsRandomOrderColumn,
        table=table
      ))
    items = convertFromFlat(items, responseColumnsNum)
  }

  if(itemBankType == "direct") {
    itemBankItems = fromJSON(itemBankItems)
    if(length(itemBankItems) > 0) {
      for(i in 1:length(itemBankItems)) {
        itemBankItems[[i]]$responseOptions = as.character(toJSON(itemBankItems[[i]]$responseOptions)) #response options don't fit into flat table, so turn them back to JSON.
        items = rbind(items, data.frame(itemBankItems[[i]], stringsAsFactors=F))
      }
    }
  } 

  if(!is.na(settings$itemBankFilterModule) && settings$itemBankFilterModule != "") {
    items = concerto.test.run(settings$itemBankFilterModule, params=list(
      settings = settings,
      session=session,
      items=items
    ))$items
  }

  if(dim(items)[1] == 0) { stop("Item bank must not be empty!") }

  if(settings$order == "manual") {
    items = items[order(items$fixedIndex),]
  }
  if(settings$order == "random") {
    items = items[sample(1:dim(items)[1]),]
  }

  return(items)
}

getParamsNum = function(itemBankType, itemBankTable, itemBankFlatTable) {
  paramsNum = 9

  if(itemBankType == "table") {
    tableMap = fromJSON(itemBankTable)
    paramsNum = getIndicedColumnsNum(tableMap$table, tableMap$columns$p1)
  }

  if(itemBankType == "flatTable") {
    tableMap = fromJSON(itemBankFlatTable)
    paramsNum = getIndicedColumnsNum(tableMap$table, tableMap$columns$p1)
  }

  return(paramsNum)
}

theta = as.numeric(settings$startingTheta)
itemsAdministered = NULL
testTimeStarted = as.numeric(Sys.time())
totalTimeTaken = 0
resumedItemsIds = NULL
direction = 1
page = 0
scores = NULL
responses = NULL

state = list(
  testTimeStarted = testTimeStarted,
  nextItemsIds = NULL,
  page = 0
)

paramsNum = getParamsNum(settings$itemBankType, settings$itemBankTable, settings$itemBankFlatTable)
items = getItems(settings$itemBankType, settings$itemBankItems, settings$itemBankTable, settings$itemBankFlatTable, settings$itemBankTableExtraFields, paramsNum)
itemsNum = dim(items)[1]

if(settings$sessionResuming == 1) {
  #get response data
  sessionTable = fromJSON(settings$sessionTable)
  resumedState = session[[sessionTable$columns$state]]
  if(!is.na(resumedState)) {
    state = fromJSON(resumedState)

    direction = 0
    page = state$page

    sessionTestTimeStarted = as.numeric(state$testTimeStarted)
    if(sessionTestTimeStarted != 0) {
      testTimeStarted = sessionTestTimeStarted
    }

    responseTable = fromJSON(settings$responseBank)
    responsesRecords = concerto.table.query("
SELECT id, 
{{scoreCol}} AS score, 
{{timeTakenCol}} AS timeTaken,
{{itemIdCol}} AS item_id,
{{responseCol}} AS response
FROM {{table}} 
WHERE {{sessionIdCol}}={{sessionId}}", params=list(
  scoreCol = responseTable$columns$score,
  timeTakenCol = responseTable$columns$timeTaken,
  itemIdCol = responseTable$columns$item_id,
  responseCol = responseTable$columns$response,
  table = responseTable$table,
  sessionIdCol = responseTable$columns$session_id,
  sessionId = session$id
))

    itemsAnswered = items[items[,"id"] %in% responsesRecords[,"item_id"],]
    if(dim(itemsAnswered)[1] > 0) {
      itemsLeft = items[-as.numeric(rownames(itemsAnswered)),]
      items = rbind(itemsAnswered, itemsLeft)
    }

    resumedItemsIds = state$nextItemsIds
    if(length(resumedItemsIds) == 0) { 
      resumedItemsIds = NULL
    }

    totalTimeTaken = sum(responsesRecords[,"timeTaken"])
    itemsAdministered = which(items[,"id"] %in% responsesRecords[,"item_id"])
    if(length(itemsAdministered) == 0) {
      itemsAdministered = NULL
    }
    scores = responsesRecords[,"score"]
    responses = responsesRecords[,"response"]
  }
}

paramBank = items[, paste0("p", 1:paramsNum), drop=F]
paramBank = apply(paramBank, 2, as.numeric)
if(is.vector(paramBank)) { 
  paramBank = rbind(paramBank)
}